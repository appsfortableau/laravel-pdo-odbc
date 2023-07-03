# Custom Processor / QueryGrammar / SchemaGrammar

To use a custom class instead of the default one, you can update your connection
configuration as follows:

```php
'snowflake-connection' => [
    //...
    'options' => [
        'processor' => Illuminate\Database\Query\Processors\Processor::class,
        'grammar' => [
            'query' => Illuminate\Database\Query\Grammars\Grammar::class,
            'schema' => Illuminate\Database\Schema\Grammars\Grammar::class,
        ]
    ]
]
```

> Values given above are the defaults.
