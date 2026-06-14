import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score
import joblib

df = pd.read_csv("data/data_finish.csv")

df_sample = df.sample(frac=1.0, random_state=42)

x = df_sample[['Tn', 'Tx', 'Tavg', 'RH_avg', 'ss', 'ff_x', 'ddd_x', 'ff_avg']]
y = df_sample['flood']

x_train, x_test, y_train, y_test = train_test_split(
    x, y, test_size=0.2, random_state=42
)

model = RandomForestClassifier(n_estimators=200, max_depth=10, random_state=42, n_jobs=-1)
model.fit(x_train, y_train)

y_pred = model.predict(x_test)
print(f"Persentase banjir berdasarkan suhu dan cuaca: {accuracy_score(y_test, y_pred) * 100:.2f}%")

joblib.dump(model, "models/deteksi_banjir_berdasarkan_cuaca.pkl")
print("Model tersimpan di dalam deteksi_banjir_berdasarkan_cuaca.pkl")