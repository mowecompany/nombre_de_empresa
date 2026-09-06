# Carpeta de base de datos

Este proyecto ahora soporta SQLite como alternativa a MySQL.

- El archivo SQLite recomendado es `database/database.db`.
- El archivo se crea automáticamente si no existe.
- Para evitar comprometer datos locales, esta carpeta ignora archivos `.db`.

Para activar SQLite, define `DB_CONNECTION=sqlite` en `Config/.env` o en el entorno.
