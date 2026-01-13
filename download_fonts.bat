@echo off
mkdir fonts 2>nul

echo Downloading Roboto...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/apache/roboto/Roboto-Regular.ttf" "fonts\Roboto.ttf"
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/apache/roboto/Roboto-Bold.ttf" "fonts\Roboto-Bold.ttf"

echo Downloading OpenSans...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/opensans/OpenSans-Regular.ttf" "fonts\OpenSans.ttf"

echo Downloading Poppins...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/poppins/Poppins-Regular.ttf" "fonts\Poppins.ttf"
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/poppins/Poppins-Bold.ttf" "fonts\Poppins-Bold.ttf"

echo Downloading GreatVibes...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/greatvibes/GreatVibes-Regular.ttf" "fonts\GreatVibes.ttf"

echo Downloading DancingScript...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/dancingscript/DancingScript-Regular.ttf" "fonts\DancingScript.ttf"

echo Downloading Montserrat...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/montserrat/Montserrat-Regular.ttf" "fonts\Montserrat.ttf"

echo Downloading Oswald...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/oswald/Oswald-Regular.ttf" "fonts\Oswald.ttf"

echo Downloading Pacifico...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/pacifico/Pacifico-Regular.ttf" "fonts\Pacifico.ttf"

echo Downloading Pinyon Script...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/pinyonscript/PinyonScript-Regular.ttf" "fonts\PinyonScript.ttf"

echo Downloading Lobster...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/lobster/Lobster-Regular.ttf" "fonts\Lobster.ttf"

echo Downloading Parisienne...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/parisienne/Parisienne-Regular.ttf" "fonts\Parisienne.ttf"

echo Downloading Allura...
certutil -urlcache -split -f "https://github.com/google/fonts/raw/main/ofl/allura/Allura-Regular.ttf" "fonts\Allura.ttf"

echo Done!
