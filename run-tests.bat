@echo off
REM Quick test runner for payroll refactoring project

echo ========================================
echo Payroll System Test Runner
echo ========================================
echo.

:menu
echo Select tests to run:
echo.
echo 1. Backend Tests (PHPUnit)
echo 2. Frontend Tests (Angular)
echo 3. Backend + Frontend (All Tests)
echo 4. Backend Coverage Report
echo 5. Frontend Coverage Report
echo 6. Exit
echo.

set /p choice="Enter choice (1-6): "

if "%choice%"=="1" goto backend
if "%choice%"=="2" goto frontend
if "%choice%"=="3" goto all
if "%choice%"=="4" goto backend_coverage
if "%choice%"=="5" goto frontend_coverage
if "%choice%"=="6" goto end

echo Invalid choice!
goto menu

:backend
echo.
echo Running Backend Tests...
echo ========================================
cd api
call vendor\bin\phpunit --colors=always
cd ..
echo.
pause
goto menu

:frontend
echo.
echo Running Frontend Tests...
echo ========================================
cd aukw-shop
call ng test --watch=false --browsers=ChromeHeadless
cd ..
echo.
pause
goto menu

:all
echo.
echo Running All Tests...
echo ========================================
echo.
echo [1/2] Backend Tests...
cd api
call vendor\bin\phpunit --colors=always
set backend_result=%ERRORLEVEL%
cd ..
echo.
echo [2/2] Frontend Tests...
cd aukw-shop
call ng test --watch=false --browsers=ChromeHeadless
set frontend_result=%ERRORLEVEL%
cd ..
echo.
echo ========================================
echo Test Results:
if %backend_result%==0 (
    echo Backend: PASSED
) else (
    echo Backend: FAILED
)
if %frontend_result%==0 (
    echo Frontend: PASSED
) else (
    echo Frontend: FAILED
)
echo ========================================
echo.
pause
goto menu

:backend_coverage
echo.
echo Generating Backend Coverage Report...
echo ========================================
cd api
call vendor\bin\phpunit --coverage-html reports\coverage
cd ..
echo.
echo Opening coverage report...
start api\reports\coverage\index.html
echo.
pause
goto menu

:frontend_coverage
echo.
echo Generating Frontend Coverage Report...
echo ========================================
cd aukw-shop
call ng test --watch=false --code-coverage --browsers=ChromeHeadless
cd ..
echo.
echo Opening coverage report...
start aukw-shop\coverage\aukw-shop\index.html
echo.
pause
goto menu

:end
echo.
echo Goodbye!
exit
