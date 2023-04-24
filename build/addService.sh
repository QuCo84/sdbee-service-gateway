cp model/* deployments/$2
cp sdbee-service-library/$1 deployments/$2
cd deployments/$1
php ../../build/buildComposerProject.php
cd ../..