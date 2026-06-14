import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import r2_score
import joblib

df = pd.read_csv("data/data_finish.csv")

kolom = ['Tn', 'Tx', 'Tavg', 'RH_avg', 'ss', 'ff_x', 'ddd_x', 'ff_avg', 'RR']
df = df.dropna(subset=kolom)

df_sample = df.sample(frac=1.0, random_state=42)

x = df_sample[['Tn', 'Tx', 'Tavg', 'RH_avg', 'ss', 'ff_x', 'ddd_x', 'ff_avg']]
y = df_sample['RR']

x_train, x_test, y_train, y_test = train_test_split(
    x, y, test_size=0.2, random_state=42
)

model = RandomForestRegressor(n_estimators=200, max_depth=10, random_state=42, n_jobs=-1)
model.fit(x_train, y_train)

y_pred = model.predict(x_test)
print(f"Prediksi curah hujan: {r2_score(y_test, y_pred):.4f}")
joblib.dump(model, "models/prediksi_curah_hujan.pkl")
print("Model tersimpan di dalam prediksi_curah_hujan.pkl")