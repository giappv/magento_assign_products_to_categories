# Import new categories & assign products to categories in Magento 1.9.x.x

## Setup

1. Create your csv files with this format: `sku,cat1;cat2;cat3;cat4;cat5`
2. Place your csv files at `/magento_root/var/import/productcategories`
3. Make sure you set your root category `define('ROOT_CATEGORY', 2);`
4. Place this file `assignproductstocategories.php` in your magento root folder

## Importing

1. Visit `yourmagentosite.com/assignproductstocategories.php?do=init` to create a queue
2. Visit `yourmagentosite.com/assignproductstocategories.php?do=updatequeue` to update csv data into queue
3. Visit `yourmagentosite.com/assignproductstocategories.php?do=process` to process the queue

## Notes
1. You can empty the queue by running `yourmagentosite.com/assignproductstocategories.php?do=resetqueue`

## Expected Output
magento root category
	> cat1 
		> cat2
			> cat3
				> cat4
Products with `sku` belongs to all these categories
