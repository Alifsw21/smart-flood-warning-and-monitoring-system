#!/usr/bin/env bash
# Deploy Smart Flood Warning stack to Kubernetes (Spec §6.2 / S5).
# Prerequisites: kubectl, cluster with StorageClass + metrics-server (HPA), nginx IngressClass.
# Run from repo root: ./k8s/deploy.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Ensuring namespace exists"
kubectl apply -f k8s/namespace.yaml

echo "==> Creating mysql-init ConfigMap from database/*.sql"
kubectl create configmap mysql-init \
  --from-file=01-schema.sql=database/schema.sql \
  --from-file=02-seed.sql=database/seed.sql \
  --namespace=smartcity \
  --dry-run=client -o yaml \
  | kubectl apply -f -

echo "==> Applying workloads via kustomize"
kubectl apply -k k8s/

echo "==> Waiting for core pods (first MySQL init may take several minutes)"
kubectl wait --for=condition=ready pod -l app=mysql -n smartcity --timeout=300s || true
kubectl wait --for=condition=ready pod -l app=api-gateway -n smartcity --timeout=300s || true

kubectl get pods,svc,ingress,hpa -n smartcity
