# GUÍA DE INSTALACIÓN Y DESPLIEGUE PASO A PASO
## SISTEMA DE GESTIÓN DE CITAS MÉDICAS "CLÍNICA VIDA SALUD"
**Materia:** Sistemas de Información 1 (UAGRM) — Semestre II-2026  
**Docente:** MSc. Ing. Angélica Gazón Cuéllar  
**Estudiante:** Jose Carlos  
**Stack Tecnológico:** PHP 8.2+ Nativo (MVC Puro) | PostgreSQL 15/16 con GiST | Bootstrap Icons Oficial Vectorial

---

## 0. ACLARACIÓN CONCEPTUAL PREVIA: ¿APACHE WEB, LOCALHOST O SERVIDOR EMBEBIDO?

> [!NOTE]
> **¿Qué quiso decir la ingeniera con "servidor Apache web"?**
> - **Apache HTTP Server** es un software tradicional de servidor web. En la universidad se suele enseñar usando paquetes como **XAMPP** o **WampServer**, los cuales vienen con Apache, MySQL, PHP y phpMyAdmin en un solo instalador.
> - **El problema de XAMPP:** Pesa más de **600 MB** el instalador y ocupa **más de 2.5 GB en disco**, trayendo servidores como MySQL, FileZilla y Mercury que **NO se usan** (nuestro proyecto utiliza **PostgreSQL**, el cual no viene en XAMPP).
> - **La solución moderna y ultraligera:** PHP 8 incluye de fábrica su propio **servidor web integrado para desarrollo**. Con solo ejecutar `php -S localhost:8000` en la terminal, tu máquina se convierte en un servidor web local idéntico a Apache, pero que ocupa **0 MB adicionales en disco** y menos de **25 MB de memoria RAM**.
> - **Si la docente exige ver la palabra "Apache":** En esta guía también te enseñamos cómo colocar el código en XAMPP si lo tuvieras, o mejor aún, cómo desplegarlo con Apache real en la nube gratuita de **Render** mediante el `Dockerfile` ya configurado en el proyecto.

---

## 1. RESUMEN DE HERRAMIENTAS Y CONSUMO DE ESPACIO

Si tu computadora tiene **muy poco espacio en disco y pocos recursos de memoria**, no instales herramientas pesadas como pgAdmin (pesa 650 MB y consume 1.5 GB de RAM). Utilizaremos el combo más liviano del mundo:

| Componente | Software Recomendado | Peso de Descarga | Espacio en Disco | ¿Por qué es el mejor? |
| :--- | :--- | :--- | :--- | :--- |
| **Lenguaje / Servidor** | **PHP 8.2 o 8.3 para Windows (Zip)** | ~35 MB | ~110 MB | No requiere instalar XAMPP ni Apache. Trae su servidor web integrado. |
| **Base de Datos** | **PostgreSQL 15 o 16 (Instalador oficial)** | ~250 MB | ~500 MB | Es el motor exigido por la docente con soporte para GiST anti-solapamiento. |
| **Cliente de BD** | **DBeaver Community** o **Extensión Database Client en VS Code** | ~110 MB (o 5 MB si es extensión) | ~200 MB | pgAdmin es un navegador web embebido muy pesado; DBeaver o la extensión de VS Code son 5 veces más rápidos y ligeros. |
| **Control de Versiones** | **Git para Windows** | ~50 MB | ~150 MB | Para manejar tu repositorio y sincronizar con GitHub. |
| **TOTAL** | | **~445 MB** | **< 1 GB** | **Ahorras más del 80% del disco** comparado a instalaciones convencionales. |

---

## 2. PASO 1: DESCARGA DEL CÓDIGO Y CREACIÓN DE TU REPOSITORIO GIT

1. Descarga el archivo `.zip` con el proyecto completo.
2. Descomprímelo en una ruta cómoda de tu disco, por ejemplo:
   - `C:\citas_medicas` (o en tu carpeta de Proyectos).
3. Abre la terminal (**PowerShell** o **Git Bash**) dentro de esa carpeta (`cd C:\citas_medicas`).
4. Inicializa tu propio repositorio local de Git ejecutando:
   ```bash
   git init
   git config user.name "Jose Carlos"
   git config user.email "tu_correo@gmail.com"
   ```
5. Registra todos los archivos en tu primer commit:
   ```bash
   git add .
   git commit -m "Primer Commit - Sistema Clinica Vida Salud Examen 2do Parcial"
   ```
6. Si deseas subirlo a tu cuenta personal de GitHub:
   - Entra a [github.com](https://github.com) y crea un nuevo repositorio vacío llamado `citas_medicas`.
   - Conéctalo y sube el código:
     ```bash
     git remote add origin https://github.com/nataly-33/citas_medicas
     git branch -M main
     git push -u origin main
     ```

---

## 3. PASO 2: INSTALAR PHP EN 3 MINUTOS (MÉTODO LIGERO SIN XAMPP)

1. **Descargar PHP:**
   - Entra a la página oficial de PHP para Windows: [https://windows.php.net/download](https://windows.php.net/download)
   - En la sección de PHP 8.2 o 8.3, descarga el archivo **VS16 x64 Thread Safe** en formato **Zip** (pesa unos 35 MB).
2. **Descomprimir:**
   - Crea una carpeta en tu disco `C:` llamada `php` (`C:\php`).
   - Extrae todo el contenido del archivo zip dentro de `C:\php`.
3. **Configurar el archivo `php.ini`:**
   - Dentro de `C:\php`, busca el archivo llamado `php.ini-development` y cámbiale el nombre a `php.ini`.
   - Abre `php.ini` con el Bloc de Notas o VS Code.
   - Presiona `Ctrl + B` (o `Ctrl + F`) para buscar y activa las siguientes líneas quitando el punto y coma (`;`) al inicio de cada una:
     ```ini
     extension_dir = "ext"
     extension=pdo_pgsql
     extension=pgsql
     extension=mbstring
     extension=openssl
     ```
   - Guarda el archivo (`Ctrl + G` o `Ctrl + S`).
4. **Agregar PHP al PATH de Windows:**
   - Presiona las teclas `Windows + R`, escribe `sysdm.cpl` y pulsa Enter.
   - Ve a la pestaña **Opciones avanzadas** y haz clic en el botón **Variables de entorno**.
   - En la lista de abajo (**Variables del sistema**), selecciona la variable **Path** y haz clic en **Editar**.
   - Haz clic en **Nuevo** y escribe: `C:\php`.
   - Haz clic en **Aceptar** en todas las ventanas abiertas.
5. **Comprobar la instalación:**
   - Cierra tu terminal actual y abre una nueva ventana de PowerShell.
   - Escribe:
     ```bash
     php -v
     ```
     *(Debe mostrarte PHP 8.2 o superior).*
   - Verifica que el driver de PostgreSQL esté activo con:
     ```bash
     php -m | findstr pgsql
     ```
     *(Debe listar `pdo_pgsql` y `pgsql`).*

---

## 4. PASO 3: INSTALAR POSTGRESQL (MOTOR DE BASE DE DATOS)

1. **Descargar el Instalador Oficial:**
   - Ve a: [https://www.enterprisedb.com/downloads/postgres-postgresql-downloads](https://www.enterprisedb.com/downloads/postgres-postgresql-downloads)
   - Descarga la versión **15** o **16** para Windows x86-64.
2. **Ejecutar el Asistente:**
   - Haz doble clic en el instalador descargado.
   - Pulsa *Next*.
   - Cuando te pregunte qué componentes instalar:
     - [x] PostgreSQL Server
     - [x] Command Line Tools
     - [ ] **Stack Builder (DESMÁRCALO para no descargar cosas innecesarias)**
     - [ ] *pgAdmin 4* (puedes desmarcarlo si no tienes espacio, o dejarlo si deseas).
   - **Contraseña del superusuario `postgres`:**
     - El instalador te pedirá que asignes una contraseña al usuario `postgres`.
     - **Recomendación:** asígnale `postgres` o `admin123` (recuerda bien cuál colocas).
   - **Puerto:** Déjalo en el predeterminado `5432`.
   - Pulsa *Next* hasta completar la instalación.

---

## 5. PASO 4: HERRAMIENTA LIVIANA PARA VER LA BD (DBeaver o VS Code)

> [!TIP]
> **No uses pgAdmin si tienes poco espacio o poca memoria RAM.** Utiliza una de estas dos opciones ultralivianas:

### Opción A: Extensión de VS Code (La más rápida y consume 0 MB extras)
1. En tu VS Code, ve a la pestaña de Extensiones (`Ctrl + Shift + X`).
2. Busca la extensión **Database Client** (o **PostgreSQL** de Chris Kolkman).
3. Instálala y crea una conexión:
   - **Host:** `localhost`
   - **Port:** `5432`
   - **User:** `postgres`
   - **Password:** la contraseña que definiste en el Paso 3.

### Opción B: DBeaver Community Edition (Excelente interfaz)
1. Descarga el instalador o la versión ZIP portable desde: [https://dbeaver.io/download/](https://dbeaver.io/download/)
2. Abre DBeaver, haz clic en **Nueva Conexión** -> **PostgreSQL**.
3. Host: `localhost`, Puerto: `5432`, Base de datos inicial: `postgres`, Usuario: `postgres`, Contraseña: tu contraseña.
4. Haz clic en **Test Connection** y luego en **Finalizar**.

---

## 6. PASO 5: CREAR LA BASE DE DATOS Y CARGAR LAS TABLAS Y DATOS

Abre una pestaña de consulta SQL en DBeaver o en tu extensión de VS Code (o directamente desde la consola `psql -U postgres`):

### 1. Crear la base de datos oficial
```sql
CREATE DATABASE db_vidasalud;
```

### 2. Conectarse a la nueva base `db_vidasalud`
En DBeaver haz doble clic en `db_vidasalud`, o en la consola ejecuta:
```sql
\c db_vidasalud
```

### 3. Ejecutar el Esquema Físico (Tablas, Triggers y GiST)
- Abre el archivo `database/esquema.sql` que viene dentro de tu proyecto.
- Copia y pega todo su contenido en tu editor SQL y ejecútalo (o ejecuta el script completo).
- Este script creará la extensión `btree_gist`, las 12 tablas del modelo relacional, las claves foráneas y la restricción física anti-solapamiento.

### 4. Cargar los Datos Iniciales de Prueba (Seeder)
- Abre el archivo `database/seeder.sql`.
- Copia y ejecuta todo su contenido en la base `db_vidasalud`.
- Esto cargará:
  - 4 Roles con sus permisos granulares.
  - 5 Especialidades médicas oficiales.
  - 5 Médicos con sus registros profesionales y horarios matutinos/vespertinos.
  - 28 Pacientes reales de Santa Cruz con tipificación sanguínea y alergias.
  - Usuarios y accesos con contraseñas seguras cifradas con Argon2id/Bcrypt (`admin123`).
  - Citas médicas programadas, confirmadas, atendidas y canceladas.

---

## 7. PASO 6: CONFIGURAR LA CONEXIÓN EN EL PROYECTO

Abre el archivo `configuration/base_datos.php` en VS Code:
```php
define('BD_HOST', getenv('BD_HOST') ?: 'localhost');
define('BD_PUERTO', getenv('BD_PUERTO') ?: '5432');
define('BD_NOMBRE', getenv('BD_NOMBRE') ?: 'db_vidasalud');
define('BD_USUARIO', getenv('BD_USUARIO') ?: 'postgres');
define('BD_CLAVE', getenv('BD_CLAVE') ?: (getenv('BD_PASSWORD') ?: 'postgres'));
```
- Si la contraseña que elegiste al instalar PostgreSQL fue `postgres`, **no necesitas cambiar nada**.
- Si elegiste otra contraseña (por ejemplo `admin123`), cambia `'postgres'` por tu contraseña en la constante `BD_CLAVE`.

---

## 8. PASO 7: INICIAR EL SERVIDOR WEB Y NAVEGAR

1. Abre tu terminal en la raíz del proyecto (`C:\citas_medicas`).
2. Ejecuta el comando para encender el servidor web local:
   ```bash
   php -S localhost:8000
   ```
3. Verás un mensaje en consola indicando:
   `Development Server (http://localhost:8000) started`
4. Abre tu navegador web favorito (Chrome, Edge o Firefox) e ingresa a:
   ```
   http://localhost:8000
   ```
   *(El sistema te redirigirá automáticamente a la pantalla de inicio de sesión de la Clínica Vida Salud).*

---

## 9. CREDENCIALES DE PRUEBA OFICIALES PARA LA DEFENSA

> [!IMPORTANT]
> **Cero Nombres de Usuario:** En cumplimiento de las directrices del examen, no existen nombres de usuario de fantasía. Las credenciales son exclusivamente **Correo Electrónico y Contraseña** (o Cédula de Identidad en el caso de pacientes).

| Rol en el Sistema | Correo de Acceso / Identificador | Contraseña Universal | Acceso y Capacidades Clave |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@vidasalud.bo` | `admin123` | Control total: gestión de usuarios, roles, médicos, pacientes, reportes, auditoría y supervisor clínico. |
| **Recepcionista** | `recepcion@vidasalud.bo` | `admin123` | Programación de citas, confirmación de asistencia ("Pres."), reprogramación, marcado de inasistencias y cancelaciones. **Restricción RBAC:** No tiene acceso a fichas clínicas ni historiales médicos confidenciales. |
| **Médico (Cardiología)** | `carlos.justiniano@vidasalud.bo` | `admin123` | Tablero diario de pacientes, atención clínica presencial con diagnóstico obligatorio, ficha médica completa de antecedentes e historial histórico de consultas. |
| **Paciente (Juan Mercado)** | `juan.mercado@gmail.com` *(o CI: `8934521`)* | `admin123` | Panel de autoconsulta, programación de sus propias citas médicas y visualización de su estado de citas y ficha personal. |

---

## 10. PASO 8: AUDITORÍA DE CALIDAD Y CERTIFICACIÓN TÉCNICA (QA)

Antes de tu defensa con la Ing. Angélica Gazón, verifica que tu instalación local cumple con el 100% de los requisitos ejecutando la suite automatizada de pruebas:

1. Abre una segunda ventana de terminal en la carpeta del proyecto.
2. Ejecuta:
   ```bash
   php pruebas_certificacion_qa.php
   ```
3. **Resultado esperado:**
   ```
   ======================================================================
    RESUMEN AUDITORÍA: 20 Aprobadas | 0 Fallidas de 20 Totales
   ======================================================================
   ```
   - 10 Pruebas mínimas obligatorias del Examen Presencial (CU01 al CU13).
   - 5 Ampliaciones técnicas (Login por CI, bloqueo de horas pasadas, inasistencias formales, ficha médica integral).
   - 5 Estándares técnicos (Paginación de 15 filas, Cero Emojis 100% vectorial, Restricción GiST en PostgreSQL, Puertos libres).

---

## 11. PLAN B: GUÍA PARA DESPLEGAR GRATIS EN RENDER (NUBE)

Si la docente pide ver el sistema en un servidor web en la nube, o por si deseas tener un respaldo en vivo accesible desde cualquier celular o computadora, sigue estos pasos:

### ¿Por qué Render?
- Ofrece **PostgreSQL gratis** y **Servidor Web gratis**.
- Ya hemos incluido en el repositorio un archivo `Dockerfile` optimizado con Apache y PHP 8.2 con drivers de PostgreSQL instalados.

### Paso a Paso para Desplegar en Render:

1. **Crear cuenta en Render:**
   - Entra a [render.com](https://render.com) y crea tu cuenta gratuita vinculándola con tu GitHub.

2. **Crear la Base de Datos PostgreSQL en Render:**
   - En el Dashboard de Render, haz clic en **New +** y selecciona **PostgreSQL**.
   - Nombre: `db-vidasalud`.
   - Database: `db_vidasalud`.
   - User: `postgres_vidasalud`.
   - Region: `Oregon` (o la más cercana).
   - Plan: **Free**.
   - Haz clic en **Create Database**.
   - Una vez creada, Render te mostrará la sección **Connections**. Copia el **External Database URL**.

3. **Cargar las Tablas en la Base de Render:**
   - Abre DBeaver en tu PC.
   - Crea una nueva conexión PostgreSQL pegando los datos de Render (Host, Database, User, Password, Port).
   - Ejecuta primero `database/esquema.sql` y luego `database/seeder.sql`.

4. **Crear el Web Service en Render:**
   - En el Dashboard de Render, haz clic en **New +** y selecciona **Web Service**.
   - Selecciona tu repositorio de GitHub `citas_medicas`.
   - Render detectará automáticamente el archivo `Dockerfile`.
   - En la sección **Environment Variables**, agrega una nueva variable:
     - **Key:** `DATABASE_URL`
     - **Value:** Pega el **Internal Database URL** que te da la base de datos de Render (o el External si prefieres).
   - Plan: **Free**.
   - Haz clic en **Deploy Web Service**.

5. **¡Listo!**
   - En 3 minutos Render compilará el contenedor Docker e iniciará el servidor web Apache con PHP 8.2.
   - Te proporcionará un enlace público HTTPS (ejemplo: `https://clinica-vidasalud-xxx.onrender.com`).
   - Podrás abrirlo y defender el examen directamente desde ese enlace o desde tu `localhost:8000`.

---

## 12. PREGUNTAS FRECUENTES PARA LA DEFENSA

**P: ¿Por qué no usamos XAMPP?**  
*R: Porque XAMPP está diseñado para MySQL/MariaDB. Nuestro sistema fue desarrollado sobre PostgreSQL utilizando tipos de datos avanzados y la extensión `btree_gist` para garantizar a nivel de motor de base de datos que ningún médico pueda tener dos citas simultáneas en el mismo horario. Usar el servidor embebido de PHP o el contenedor Docker nos ahorra gigabytes de espacio y garantiza compatibilidad absoluta.*

**P: ¿Qué pasa si una cita se cancela?**  
*R: La cita pasa al estado 'Cancelada' con su motivo registrado para fines de auditoría, y la restricción física `WHERE (estado <> 'Cancelada')` libera inmediatamente el cupo horario para que cualquier otro paciente pueda reservar ese turno.*

**P: ¿Por qué la recepcionista no ve las fichas médicas?**  
*R: Por el principio de privilegio mínimo y la confidencialidad médico-paciente. La recepcionista solo administra la logística de agendas, mientras que los diagnósticos, tipo de sangre, alergias y antecedentes clínicos son de acceso exclusivo para el personal médico asignado y el administrador supervisor.*
