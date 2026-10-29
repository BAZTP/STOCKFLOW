@echo off
title StockFlow ERP
echo ==============================================
echo  Iniciando StockFlow ERP con SQL Server...
echo ==============================================
echo.
echo Servidor Local: http://localhost:8000
echo Servidor Red:   http://192.168.100.15:8000 (o tu IP local)
echo Usuario:        admin@stockflow.local
echo Password:       Admin12345678!
echo.
"C:\Users\andre\AppData\Local\Programs\php85\php.exe" artisan serve --host=0.0.0.0 --port=8000
pause
