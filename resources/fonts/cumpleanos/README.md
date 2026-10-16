# Tipografías de las felicitaciones de cumpleaños

Las usa `App\Services\Noticias\CumpleanosImagen` (GD dibuja el nombre con ellas) y el editor
del frontend las descarga por `GET /api/noticias/cumpleanos/fuentes/{archivo}` para que el
lienzo se vea igual que la imagen final. Agregar una familia = copiar sus `.ttf` estáticos
aquí y registrarla en `CumpleanosImagen::FUENTES` (los `.ttf` variables no sirven: GD solo
dibuja la instancia por defecto).

Todas son de Google Fonts (github.com/google/fonts, carpeta `ofl/`) bajo la SIL Open Font
License 1.1 (`OFL.txt`): Poppins, PT Serif, Lilita One, Pacifico y Great Vibes.
