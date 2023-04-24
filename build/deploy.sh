cd deployments/$1
composer install
sudo apt-get -y install php
composer require google/cloud-functions-framework
php ../../build/buildYAML.php $1 $2
gcloud builds submit --region=$1
php ../../build/buildServicesExport.php $1 $2
cd ../..
