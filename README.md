# Catalog porter
######Yii2 ready wrapper for simple CSV exporting and importing  

#instalation
`The preferred way to install this extension is through composer.`

####Install
   Either run   
```bash
$ php composer.phar require max-commerce/catalog-export-import "*"
```
or add
```json
"max-commerce/catalog-export-import": "*"
```   
to the require section of your composer.json file.

####Configurate 

```php
//in your app config:
'components' => [
    ...
    'importExport' => [
                'class' => \maxcom\catalog\exportimport\components\ImportExport::class, //Component
                'exportQuery' =>  \common\models\ShopProducts::find() // This is query that u want to export
                    ->select([
                        'shop_products.sku as SKU',
                        'shop_products.name as NAME',
                        'shop_products.description as DESCRIPTION',
                        'shop_products.sale as SALE',
                        'shop_products.new as NEW',
                        'shop_products.is_available as AVAILABLE',
                        'shop_products.active as ACTIVE',
                        'shop_products.discount as DISCOUNT',
                        'shop_products.default_price AS DEFAULT_PRICE',
                        'shop_products.price AS PRICE',
                        'shop_brands.name as BRAND',
                    ])
                    ->leftJoin(\common\models\ShopCategory::tableName(), 'shop_category.id = shop_products.category_id')
                    ->leftJoin(\common\models\ShopBrands::tableName(), 'shop_brands.id = shop_products.brand_id'),
                'attributesToNames' => [ //This is labels for head in export table by attributes from upper query
                    'SKU' => 'Артикул',
                    'NAME' => 'Название',
                    'DESCRIPTION' => 'Описание',
                    'SALE' => 'Со скидкой',
                    'NEW' => 'Новинка',
                    'AVAILABLE' => 'Доступен',
                    'ACTIVE' => 'Активен',
                    'DISCOUNT' => 'Скидка',
                    'DEFAULT_PRICE' => 'Базовая цена',
                    'PRICE' => 'Цена',
                    'BRAND' => 'Бренд',
                    'CATEGORY' => 'Категория',
                ]
            ],
    ...
],
//Here u can add a module for working with build-in export/import controller
'modules' => [
    'import-export' => [
        'class' => \maxcom\catalog\exportimport\Module::class
    ]
]



//otherside u can include our import/export actions into your controller with manual below
//In your controller:

...
public function actions() {
        return [
            ...
                'export' => [
                    'class' => '\maxcom\catalog\exportimport\actions\ExportAction',
                ],
                'import' => [
                    'class' => '\maxcom\catalog\exportimport\actions\ImportAction'
                ]
            ...
        ];
}
...

//Also you can use component without Yii2 Container 
//like:
// $porter = new ImportExport([
    'exportQuery' => 'Your export ActiveQuery',
    'attributesToNames' => 'Your human understanding lables' 
]);
    $result = $porter->export(); 
    ...
```



