CAJA 1 - INSTALACION LOCAL

Este archivo es para la computadora principal.
La Caja 1 es la unica que necesita XAMPP, Apache, PHP y SQLite.

REQUISITOS:
1. Instalar XAMPP
2. Copiar la carpeta del proyecto a:
   C:\xampp\htdocs\nombre_de_empresa
3. Iniciar Apache desde XAMPP
4. Abrir la URL:
   http://localhost/nombre_de_empresa/

ACCESO RAPIDO:
- Ejecuta el archivo: instalar_caja1.bat
- O abre este archivo URL:
  Caja1-Acceso-Directo.url

IMPORTANTE:
- Caja 2 y Caja 3 NO necesitan XAMPP ni PHP ni SQLite.
- Caja 2 y Caja 3 solo deben abrir la URL local del servidor:
  http://192.168.1.100/nombre_de_empresa/

BASE DE DATOS:
- La base de datos SQLite queda en:
  C:\xampp\htdocs\nombre_de_empresa\database\database.db

RED LOCAL:
- La Caja 1 debe tener una IP fija como:
  192.168.1.100
- Las otras cajas deben estar conectadas a la misma red Wi-Fi local.
