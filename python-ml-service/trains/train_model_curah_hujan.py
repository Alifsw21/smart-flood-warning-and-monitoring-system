"""Train rainfall category classifier (spec: Accuracy >= 70%)."""

import sys
from pathlib import Path

import joblib
from sklearn.metrics import accuracy_score
from sklearn.model_selection import cross_val_score, train_test_split

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from rainfall_pipeline import CV_FOLDS, RANDOM_STATE, prepare_training_frame, train_rainfall_bundle

DATA_PATH = ROOT / "data" / "data_finish.csv"
MODEL_PATH = ROOT / "models" / "prediksi_curah_hujan.pkl"


def main() -> None:
    df = prepare_training_frame(str(DATA_PATH))
    bundle = train_rainfall_bundle(str(DATA_PATH))

    x = df[bundle["features"]]
    y = df["category_id"]
    x_train, x_test, y_train, y_test = train_test_split(
        x, y, test_size=0.2, random_state=RANDOM_STATE, stratify=y
    )

    cv_scores = cross_val_score(
        bundle["classifier"],
        x_train,
        y_train,
        cv=CV_FOLDS,
        scoring="accuracy",
        n_jobs=-1,
    )
    test_acc = accuracy_score(y_test, bundle["classifier"].predict(x_test))

    MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(bundle, MODEL_PATH)

    print(f"Cross-validation Accuracy ({CV_FOLDS}-fold): {cv_scores.mean() * 100:.2f}%")
    print(f"Hold-out test Accuracy: {test_acc * 100:.2f}%")
    print(f"Status spesifikasi (>=70%): {'LULUS' if cv_scores.mean() >= 0.70 else 'PERLU TUNING'}")
    print(f"Model tersimpan -> {MODEL_PATH}")


if __name__ == "__main__":
    main()
