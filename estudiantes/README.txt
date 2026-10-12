CAFI - Menú normal y productos especiales separados

Comportamiento:
- Menú semanal:
  * muestra siempre el producto normal;
  * usa PrecioBase;
  * no toma PrecioEspecial;
  * Ver más también muestra PrecioBase.

- Especiales:
  * muestra el producto especial/promoción;
  * usa PrecioEspecial;
  * si PrecioEspecial < PrecioBase, muestra PrecioBase tachado;
  * si PrecioEspecial >= PrecioBase, muestra solamente PrecioEspecial en rojo;
  * Ver detalles de la promoción muestra PrecioEspecial.

Esto evita que un producto del menú semanal cambie de precio o apariencia
solo porque también existe una entrada activa en ProductosEspeciales.

Archivos a reemplazar:
- estudiantes/app.js

Los demás archivos se incluyen para mantener el paquete completo.
