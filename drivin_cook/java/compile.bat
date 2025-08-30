@echo off

echo ========================================
echo Compilation du module
echo ========================================

java -version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERREUR: Java n'est pas installé ou pas dans le PATH
    echo Veuillez installer Java JDK et l'ajouter au PATH
    pause
    exit /b 1
)

javac -version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERREUR: Le compilateur Java (javac) n'est pas disponible
    echo Veuillez installer Java JDK (pas seulement JRE)
    pause
    exit /b 1
)

echo Java detecte, compilation en cours...

javac LoyaltyManager.java

if %ERRORLEVEL% EQU 0 (
    echo SUCCESS: LoyaltyManager.java compile avec succes
    echo Le fichier LoyaltyManager.class a ete cree
    echo.
    echo Test du module...
    
    java LoyaltyManager getCurrentPoints 1
    
    if %ERRORLEVEL% EQU 0 (
        echo Test OK: Le module Java fonctionne correctement
    ) else (
        echo ATTENTION: Erreur lors du test - Verifiez la base de donnees
    )
) else (
    echo ERREUR: Echec de la compilation
    echo Verifiez le code Java et reessayez
)

echo.
echo ========================================
echo Compilation terminee
echo ========================================
pause