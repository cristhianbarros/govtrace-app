# BlazeFace (detector de rostros)

Modelo de detección de rostros de Google, en el formato de TensorFlow.js
(`tensorflow/tfjs-model/blazeface/1/default/1`, de TensorFlow Hub / Kaggle
Models). Licencia Apache-2.0: https://www.apache.org/licenses/LICENSE-2.0

GovTrace lo aloja aquí (it. 46e, R-PRIV-05) para que la foto nunca salga del
celular: el navegador lo descarga de GovTrace, lo guarda el Service Worker y
detecta los rostros sin enviar la foto a nadie.

- `model.json`: el grafo y la lista de pesos.
- `group1-shard1of1.bin`: los pesos (unos 400 KB).
