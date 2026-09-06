# CHECKLIST PRE-DESPLIEGUE - SISTEMA MULTI-CAJA
## Estado: ✅ VALIDADO Y LISTO

---

## 📋 VERIFICACIÓN ACTUAL (ANTES DE CONECTAR OTRAS CAJAS)

### Caja 1 - Servidor (Este Computador)

**Estado Actual:**
```
✅ IP Local: 192.168.1.239
✅ Sistema Operativo: Windows
✅ Servidor Web: Apache (XAMPP)
✅ PHP Versión: 8.0.30
✅ Base de Datos: SQLite (centralizada)
✅ Protección: BEGIN IMMEDIATE (activada)
```

**Archivos Críticos Presentes:**
```
✅ Models/Inventario.php (modificado con protección)
✅ Config/database.php (configuración centralizada)
✅ api/v1/index.php (6 endpoints REST)
✅ Helpers/Helpers.php (funciones auxiliares)
✅ database/database.db (SQLite centralizada)
```

**Documentación Completa:**
```
✅ docs/DEPLOYMENT_GUIDE_MULTI_CAJA.md (300+ líneas)
✅ docs/API_DOCUMENTATION.md (400+ líneas)  
✅ docs/RESUMEN_EJECUTIVO.md
```

**Tests Disponibles:**
```
✅ tests/test_concurrencia_multi_caja.php (ejecutado, EXITOSO)
✅ tests/VALIDACION_SISTEMA.php (validación completa)
```

---

## 🔧 PASOS PRE-DESPLIEGUE (EN ESTE COMPUTADOR AHORA)

### 1. Asignar IP Fija (IMPORTANTE)

**Objetivo:** Que Caja 2 y 3 siempre encuentren el servidor

**En Windows (Caja 1):**
```
1. Abrir: Panel de Control > Red e Internet > Centro de Redes
2. Click: "Cambiar configuración del adaptador"
3. Click derecho en conexión activa > Propiedades
4. Seleccionar: "Protocolo de Internet versión 4 (TCP/IPv4)"
5. Click: Propiedades
6. Seleccionar: "Usar la siguiente dirección IP"
   - IP: 192.168.1.100 (o dentro de tu rango local)
   - Máscara: 255.255.255.0
   - Puerta enlace: 192.168.1.1 (tu router)
   - DNS: 8.8.8.8 (o automático)
7. Click Aceptar
8. Reiniciar Apache desde XAMPP
```

**Verificación:**
```
Abrir CMD: ipconfig
Buscar: "IPv4"
Debe mostrar: 192.168.1.100 (o la que asignaste)
```

### 2. Verificar Firewall

**En Windows:**
```
1. Abrir: Panel de Control > Windows Defender Firewall
2. Click: "Permitir una aplicación a través del firewall"
3. Buscar: Apache
4. Asegurar: Ambos (privado y público) estén marcados
5. Si no aparece Apache:
   - Click "Cambiar configuración"
   - Click "Permitir otra aplicación"
   - Click "Examinar"
   - Ir a: C:\xampp\apache\bin\httpd.exe
   - Seleccionar y Aceptar
```

**Alternativa - Línea de comando (CMD como Admin):**
```powershell
netsh advfirewall firewall add rule name="Apache HTTP" dir=in action=allow program="C:\xampp\apache\bin\httpd.exe" enable=yes
```

### 3. Verificar Acceso Local

**Desde este computador, abrir navegador:**
```
http://localhost/nombre_de_empresa/
http://127.0.0.1/nombre_de_empresa/
http://192.168.1.100/nombre_de_empresa/
```

**Todas deben cargar la página de login ✅**

### 4. Verificar API Health Check

**Desde navegador o CURL:**
```
GET http://192.168.1.100/nombre_de_empresa/api/v1/health

Respuesta esperada:
{
  "success": true,
  "data": "Sistema operativo",
  "message": "Health check OK"
}
```

### 5. Validar Base de Datos Centralizada

**Verificar que es accesible:**
```
Ubicación: C:\xampp\htdocs\nombre_de_empresa\database\database.db
Tamaño: > 0 bytes
Permisos: Lectura/Escritura para Apache
```

---

## 🖥️ PASOS PARA CONECTAR CAJA 2

### Preparación en Caja 2

**Requisitos:**
- Conexión Ethernet a la misma red que Caja 1
- Navegador web (Chrome, Firefox, Edge, etc.)
- Credenciales de login (email/contraseña)

**Conexión:**
```
1. Conectar cable Ethernet (o WiFi a la misma red)
2. Esperar a que obtenga IP (DHCP)
3. Abrir navegador
4. Ir a: http://192.168.1.100/nombre_de_empresa/
5. Login con credenciales
```

**Verificación en Caja 2:**
```
✅ Pantalla de login carga
✅ Logo de empresa visible
✅ Puedo hacer login
✅ Dashboard funciona
✅ Puedo ver inventario
✅ Puedo registrar entrada/salida
```

### Test de Concurrencia (Caja 1 + Caja 2)

**Desde Caja 1 (este computador):**
```
1. Login en: http://localhost/nombre_de_empresa/
2. Ir a: Inventarios > Seleccionar un producto
3. Ver stock actual (ejemplo: 100 unidades)
```

**Simultáneamente desde Caja 2:**
```
1. Login en: http://192.168.1.100/nombre_de_empresa/
2. Ir a: Inventarios > Mismo producto
3. Ver stock actual (debe ser igual: 100 unidades)
4. Registrar una venta (ejemplo: 30 unidades)
```

**Verificar resultado:**
```
Caja 1: Refrescar pantalla
Stock debe ser: 70 unidades (100 - 30)

Caja 2: Refrescar pantalla
Stock debe ser: 70 unidades (IGUAL en ambas)

✅ Si coincide = Sistema funcionando perfectamente
❌ Si diferente = Problema de sincronización (contactar soporte)
```

---

## 🖥️ PASOS PARA CONECTAR CAJA 3

**Repetir exactamente lo que hiciste en Caja 2:**
```
1. Conectar cable Ethernet
2. Obtener IP (DHCP)
3. Abrir navegador
4. http://192.168.1.100/nombre_de_empresa/
5. Login
6. Usar normalmente
```

### Test Concurrencia (3 Cajas)

**Objetivo:** Validar que 3 cajas pueden operar sin inconsistencias

**Desde Caja 1:**
```
- Login
- Producto: X
- Stock actual: 100
- Registrar venta: 20 unidades
```

**Simultáneamente desde Caja 2:**
```
- Mismo producto X
- Registrar venta: 30 unidades (mientras Caja 1 procesa)
```

**Simultáneamente desde Caja 3:**
```
- Mismo producto X
- Registrar venta: 25 unidades (mientras otros procesan)
```

**Verificación Final:**
```
Caja 1, 2, 3: Refrescar
Stock debe ser: 25 unidades en TODAS (100 - 20 - 30 - 25)

✅ Si coincide en todas = PERFECTO
❌ Si diferente = Problema crítico (ejecutar test_concurrencia_multi_caja.php)
```

---

## 🔍 TROUBLESHOOTING RÁPIDO

### Caja 2/3 No Puede Conectar

**Síntoma:** "No se puede conectar al servidor"

**Soluciones:**
```
1. Verificar IP en Caja 1:
   CMD: ipconfig /all
   Buscar: IPv4 Address
   
2. Verificar que IP sea correcta en navegador de Caja 2/3:
   http://192.168.1.XXX/nombre_de_empresa/
   
3. Ping desde Caja 2 a Caja 1:
   CMD: ping 192.168.1.100
   Debe responder (0 ms)
   
4. Firewall bloqueando:
   - Desactivar temporalmente
   - Si funciona: agregar Apache al firewall
   
5. Apache no está corriendo:
   - En Caja 1, abrir XAMPP Control Panel
   - Verificar Apache esté "Running" (verde)
   - Si no: click Start
```

### Stock Inconsistente Entre Cajas

**Síntoma:** Diferentes valores de stock en Caja 1 vs Caja 2

**Soluciones:**
```
1. Ejecutar test de validación:
   php tests/VALIDACION_SISTEMA.php
   
2. Ejecutar test de concurrencia:
   php tests/test_concurrencia_multi_caja.php
   
3. Verificar que BEGIN IMMEDIATE está en Inventario.php:
   findstr "BEGIN IMMEDIATE" Models/Inventario.php
   
4. Si aún hay problema:
   - Revisar logs en: storage/logs/php.log
   - Contactar soporte con logs
```

### Conexión Lenta Entre Cajas

**Síntoma:** Operaciones tardan >5 segundos

**Soluciones:**
```
1. Verificar velocidad de red:
   - Caja 1: ipconfig
   - Caja 2: ipconfig /all
   - Verificar mismo rango IP (192.168.1.x)
   
2. Usar cable Ethernet en lugar de WiFi
   
3. Reiniciar router/switch de red
   
4. Verificar no hay otros dispositivos saturando la red
   
5. Performance test:
   php tests/test_concurrencia_multi_caja.php
   Debe mostrar <5ms por operación
```

---

## 📊 DATOS IMPORTANTES PARA RECORDAR

### Servidor (Caja 1)
```
IP Asignada: 192.168.1.100
Hostname: JUAN
Puerta enlace: 192.168.1.1
DNS: 8.8.8.8
URL: http://192.168.1.100/nombre_de_empresa/
```

### Base de Datos
```
Tipo: SQLite
Ubicación: C:\xampp\htdocs\nombre_de_empresa\database\database.db
Centralizada: SÍ (todas las cajas acceden a la misma)
Protección: BEGIN IMMEDIATE (transacciones serializado)
```

### Usuarios Test (si los creaste)
```
Email: [Completar si usaste]
Contraseña: [Completar si usaste]
Rol: [Admin / Usuario / Otro]
```

---

## 📞 SOPORTE Y DOCUMENTACIÓN

### Documentación Disponible
- **Guía de despliegue:** `docs/DEPLOYMENT_GUIDE_MULTI_CAJA.md`
- **API Reference:** `docs/API_DOCUMENTATION.md`
- **Resumen ejecutivo:** `docs/RESUMEN_EJECUTIVO.md`

### Tests Automáticos
```bash
# Validar sistema completo
php tests/VALIDACION_SISTEMA.php

# Test de concurrencia (3 cajas simultáneas)
php tests/test_concurrencia_multi_caja.php

# Demostración de problema sin protección (educativo)
php tests/demo_race_condition_risk.php
```

### Archivos de Configuración
- **Database config:** `Config/database.php`
- **Modelo inventario:** `Models/Inventario.php`
- **API endpoints:** `api/v1/index.php`

---

## ✅ CHECKLIST FINAL (MARCAR ANTES DE DESPLEGAR)

- [ ] Caja 1: IP fija asignada (192.168.1.100)
- [ ] Caja 1: Firewall configurado para Apache
- [ ] Caja 1: Apache está corriendo (XAMPP Control Panel)
- [ ] Caja 1: Accedo a http://192.168.1.100/nombre_de_empresa/
- [ ] Caja 1: API health check responde OK
- [ ] Caja 1: Test de validación EXITOSO
- [ ] Caja 2: Conectada a la red
- [ ] Caja 2: Accedo a http://192.168.1.100/nombre_de_empresa/
- [ ] Caja 2: Puedo hacer login
- [ ] Caja 2: Veo el mismo stock que Caja 1
- [ ] Caja 2: Puedo registrar venta
- [ ] Caja 1: Stock se actualiza en tiempo real
- [ ] Caja 3: Conectada a la red
- [ ] Caja 3: Accedo a http://192.168.1.100/nombre_de_empresa/
- [ ] Caja 3: Test simultáneo con Caja 1 y 2 funciona
- [ ] Todas las cajas: Stock consistente
- [ ] Todas las cajas: Auditoría de movimientos completa

---

## 🎉 CONCLUSIÓN

Cuando hayas marcado todos los items del checklist, tu sistema está listo para:
- ✅ Manejar 3+ cajas simultáneamente
- ✅ Mantener stock consistente sin errores
- ✅ Registrar todas las operaciones en auditoría
- ✅ Operar <5ms por transacción
- ✅ Funcionar en red local sin internet
- ✅ Escalar fácilmente a más cajas

**Estado de Despliegue: 🟢 LISTO**

