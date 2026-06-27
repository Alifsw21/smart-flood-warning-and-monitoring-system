#!/usr/bin/env bash
# Deploy Smart Flood Warning stack to Kubernetes (Spec §6.2 / S5).
# Prerequisites: kubectl, cluster with StorageClass + metrics-server (HPA), nginx IngressClass.
# Run from repo root: ./k8s/deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

wait_for_ingress_controller() {
  if ! kubectl get namespace ingress-nginx >/dev/null 2>&1; then
    echo "==> ingress-nginx namespace not found; skipping Ingress wait/apply"
    echo "    Install controller first, then re-run: kubectl apply -f k8s/ingress.yaml"
    return 0
  fi

  echo "==> Waiting for ingress-nginx admission webhook"
  kubectl wait --namespace ingress-nginx \
    --for=condition=ready pod \
    --selector=app.kubernetes.io/component=controller \
    --timeout=180s

  echo "==> Applying Ingress (after controller is ready)"
  kubectl apply -f k8s/ingress.yaml
}

echo "==> Ensuring namespace exists"
kubectl apply -f k8s/namespace.yaml

echo "==> Creating mysql-init ConfigMap from database/*.sql"
kubectl create configmap mysql-init \
  --from-file=01-schema.sql=database/schema.sql \
  --from-file=02-seed.sql=database/seed.sql \
  --namespace=smartcity \
  --dry-run=client -o yaml \
  | kubectl apply -f -

echo "==> Applying workloads via kustomize (Ingress applied separately)"
kubectl apply -k k8s/

wait_for_ingress_controller

echo "==> Waiting for core pods (first MySQL init may take several minutes)"
kubectl wait --for=condition=ready pod -l app=mysql -n smartcity --timeout=300s || true
kubectl wait --for=condition=ready pod -l app=rabbitmq -n smartcity --timeout=300s || true
kubectl wait --for=condition=ready pod -l app=api-gateway -n smartcity --timeout=300s || true

kubectl get pods,svc,ingress,hpa -n smartcity
