# Import new categories & assign products to categories

# Setup

1. Place your csv files at `/magento_root/var/import/productcategories`
2. Make sure you set your root category `define('ROOT_CATEGORY', 2);`
3. Place this file in your magento root folder

# Importing

1. Visit `yourmagentosite.com/assignproductstocategories.php?do=init` to create a queue
2. Visit `yourmagentosite.com/assignproductstocategories.php?do=updatequeue` to update csv data into queue
3. Visit `yourmagentosite.com/assignproductstocategories.php?do=process` to process the queue
