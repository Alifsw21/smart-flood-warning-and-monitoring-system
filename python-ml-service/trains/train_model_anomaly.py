"""Train Isolation Forest anomaly detector (Spec §4.4 MODEL 3)."""

from pathlib import Path

import joblib
import numpy as np
from sklearn.ensemble import IsolationForest
from sklearn.preprocessing import StandardScaler

ANOMALY_FEATURES = ["sensor_value", "timestamp_hour", "rolling_mean_1h", "z_score"]
MODEL_PATH = Path(__file__).resolve().parent.parent / "models" / "deteksi_anomali.pkl"

rng = np.random.default_rng(42)
n = 5000

normal = int(n * 0.95)
anomaly = n - normal

sensor_value = np.concatenate([
    rng.normal(4.5, 1.0, normal),
    rng.normal(14.0, 2.5, anomaly),
])
rolling_mean = np.concatenate([
    rng.normal(4.5, 0.4, normal),
    rng.normal(4.5, 0.6, anomaly),
])
z_score = np.concatenate([
    rng.normal(0.0, 0.6, normal),
    rng.normal(3.2, 0.8, anomaly),
])
timestamp_hour = rng.integers(0, 24, size=n)

X = np.column_stack([sensor_value, timestamp_hour, rolling_mean, z_score])
scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)

model = IsolationForest(n_estimators=200, contamination=0.05, random_state=42)
model.fit(X_scaled)

MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
joblib.dump(
    {
        "model": model,
        "scaler": scaler,
        "features": ANOMALY_FEATURES,
    },
    MODEL_PATH,
)
print(f"Anomaly model saved -> {MODEL_PATH}")
