# Instalación en Windows con SQL Server y SSMS

SQL Server almacena los datos; SQL Server Management Studio (SSMS) permite administrarlos.
Instala SQL Server 2022 Developer para desarrollo (o una edición con licencia para producción)
y SSMS. Activa autenticación mixta y TCP/IP desde SQL Server Configuration Manager,
configura un puerto fijo, por ejemplo 1433, y reinicia el servicio del motor.

También necesitas Git, PHP 8.5 x64, Composer 2, Node.js 24, Microsoft ODBC Driver 18
for SQL Server y Microsoft Drivers 5.13.3 for PHP for SQL Server. Usa las DLL que
coincidan con PHP 8.5, x64 y TS/NTS de `php -i`. En `php.ini` habilita `pdo_sqlsrv`
y `sqlsrv`, además de mbstring, bcmath, curl, fileinfo, openssl, dom, xml,
xmlwriter y zip. Verifica `php -m` y `php -r "print_r(PDO::getAvailableDrivers());"`.

Configura un certificado de servidor válido para el nombre DNS utilizado por la
aplicación. Para desarrollo puedes usar una CA local e instalar su certificado
raíz en el almacén de confianza de Windows. Configura el certificado en SQL Server
Configuration Manager y reinicia el servicio. Conserva la verificación TLS habilitada.
En SSMS selecciona cifrado obligatorio y deja desmarcada «Confiar en el certificado del servidor».

Conéctate desde SSMS como administrador y ejecuta lo siguiente, reemplazando
`CAMBIAR_CONTRASENA` por una contraseña propia antes de ejecutar:

```sql
CREATE DATABASE [stockflow];
GO
CREATE DATABASE [stockflow_test];
GO
CREATE LOGIN [stockflow] WITH PASSWORD = N'CAMBIAR_CONTRASENA';
GO
USE [stockflow];
CREATE USER [stockflow] FOR LOGIN [stockflow];
ALTER ROLE db_owner ADD MEMBER [stockflow];
GO
USE [stockflow_test];
CREATE USER [stockflow] FOR LOGIN [stockflow];
ALTER ROLE db_owner ADD MEMBER [stockflow];
GO
```

El usuario tiene permisos para migraciones en esas dos bases, sin ser administrador
del servidor. En producción separa el usuario de migraciones del usuario de ejecución.

```powershell
git clone https://github.com/BAZTP/STOCKFLOW.git
cd STOCKFLOW
Copy-Item .env.example .env
composer install
npm ci
php artisan key:generate
```

Configura `.env` sin subirlo a Git:

```dotenv
DB_CONNECTION=sqlsrv
DB_HOST=localhost
DB_PORT=1433
DB_DATABASE=stockflow
DB_USERNAME=stockflow
DB_PASSWORD="tu contraseña propia"
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=false
```

Si usas un nombre DNS distinto, debe coincidir con el certificado. Para una instancia
Express configura su puerto TCP fijo y usa ese puerto en `.env`.

Crea las tablas con `php artisan migrate --seed`. Alternativamente, abre
`database/sql/stockflow-sqlserver.sql` en SSMS, selecciona la base **stockflow vacía**
y ejecuta el archivo completo. Luego ejecuta `php artisan migrate --seed`: el script
ya registra las migraciones y Artisan sólo aplicará las pendientes y cargará roles.
El SQL no incluye contraseñas, usuarios de aplicación ni datos comerciales.

```powershell
php artisan migrate --seed
php artisan storage:link
php artisan stockflow:admin admin@tuempresa.com
npm run build
php artisan test
php artisan serve
```

El comando de administrador pide la contraseña sin mostrarla. Opcionalmente ejecuta
`php artisan stockflow:demo` para datos ficticios. Para estilos usa `npm run dev`.
Configura MAIL_* para recuperación por correo; `log` es sólo para desarrollo.

Los tests eliminan tablas de `stockflow_test`; esa base debe ser exclusiva de pruebas.
No ejecutes `migrate:fresh` sobre datos que deban conservarse.
La base MySQL anterior no se convierte ni importa automáticamente: respáldala y
planifica una transferencia de datos antes de cambiar una instalación con datos reales.
Los comprobantes internos no tienen autorización electrónica del SRI.
