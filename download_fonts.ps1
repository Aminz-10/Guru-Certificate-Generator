# download_fonts.ps1

$fonts = @{
    "Roboto.ttf" = "https://github.com/google/fonts/raw/main/apache/roboto/Roboto-Regular.ttf";
    "Roboto-Bold.ttf" = "https://github.com/google/fonts/raw/main/apache/roboto/Roboto-Bold.ttf";
    "OpenSans.ttf" = "https://github.com/google/fonts/raw/main/ofl/opensans/OpenSans-Regular.ttf";
    "Lato.ttf" = "https://github.com/google/fonts/raw/main/ofl/lato/Lato-Regular.ttf";
    "Poppins.ttf" = "https://github.com/google/fonts/raw/main/ofl/poppins/Poppins-Regular.ttf";
    "Poppins-Bold.ttf" = "https://github.com/google/fonts/raw/main/ofl/poppins/Poppins-Bold.ttf";
    "Montserrat.ttf" = "https://github.com/google/fonts/raw/main/ofl/montserrat/Montserrat-Regular.ttf";
    "Oswald.ttf" = "https://github.com/google/fonts/raw/main/ofl/oswald/Oswald-Regular.ttf";
    "GreatVibes.ttf" = "https://github.com/google/fonts/raw/main/ofl/greatvibes/GreatVibes-Regular.ttf";
    "DancingScript.ttf" = "https://github.com/google/fonts/raw/main/ofl/dancingscript/DancingScript-Regular.ttf";
    "Pacifico.ttf" = "https://github.com/google/fonts/raw/main/ofl/pacifico/Pacifico-Regular.ttf";
    "IndieFlower.ttf" = "https://github.com/google/fonts/raw/main/ofl/indieflower/IndieFlower-Regular.ttf";
    "Lobster.ttf" = "https://github.com/google/fonts/raw/main/ofl/lobster/Lobster-Regular.ttf";
    "Caveat.ttf" = "https://github.com/google/fonts/raw/main/ofl/caveat/Caveat-Regular.ttf";
    "Satisfy.ttf" = "https://github.com/google/fonts/raw/main/ofl/satisfy/Satisfy-Regular.ttf";
    "AmaticSC.ttf" = "https://github.com/google/fonts/raw/main/ofl/amaticsc/AmaticSC-Regular.ttf";
    "PermanentMarker.ttf" = "https://github.com/google/fonts/raw/main/ofl/permanentmarker/PermanentMarker-Regular.ttf";
    "AbrilFatface.ttf" = "https://github.com/google/fonts/raw/main/ofl/abrilfatface/AbrilFatface-Regular.ttf";
    "Cookie.ttf" = "https://github.com/google/fonts/raw/main/ofl/cookie/Cookie-Regular.ttf";
    "Courgette.ttf" = "https://github.com/google/fonts/raw/main/ofl/courgette/Courgette-Regular.ttf";
    "Bangers.ttf" = "https://github.com/google/fonts/raw/main/ofl/bangers/Bangers-Regular.ttf";
    "FredokaOne.ttf" = "https://github.com/google/fonts/raw/main/ofl/fredokaone/FredokaOne-Regular.ttf";
    "Righteous.ttf" = "https://github.com/google/fonts/raw/main/ofl/righteous/Righteous-Regular.ttf";
    "Sacramento.ttf" = "https://github.com/google/fonts/raw/main/ofl/sacramento/Sacramento-Regular.ttf";
    "Monoton.ttf" = "https://github.com/google/fonts/raw/main/ofl/monoton/Monoton-Regular.ttf";
    "Orbitron.ttf" = "https://github.com/google/fonts/raw/main/ofl/orbitron/Orbitron-Regular.ttf";
    "PinyonScript.ttf" = "https://github.com/google/fonts/raw/main/ofl/pinyonscript/PinyonScript-Regular.ttf";
    "Parisienne.ttf" = "https://github.com/google/fonts/raw/main/ofl/parisienne/Parisienne-Regular.ttf";
    "Allura.ttf" = "https://github.com/google/fonts/raw/main/ofl/allura/Allura-Regular.ttf";
    "AlexBrush.ttf" = "https://github.com/google/fonts/raw/main/ofl/alexbrush/AlexBrush-Regular.ttf"
}

$destDir = ".\fonts"
if (!(Test-Path -Path $destDir)) {
    New-Item -ItemType Directory -Path $destDir | Out-Null
}

Write-Host "Downloading fonts..."

foreach ($name in $fonts.Keys) {
    $url = $fonts[$name]
    $output = Join-Path $destDir $name
    
    try {
        Invoke-WebRequest -Uri $url -OutFile $output -UseBasicParsing
        Write-Host "Downloaded: $name" -ForegroundColor Green
    } catch {
        Write-Host "Failed to download $name : $_" -ForegroundColor Red
    }
}

Write-Host "Done."
