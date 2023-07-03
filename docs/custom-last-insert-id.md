# Custom `getLastInsertId()` Function

If you need to provide a custom `getLastInsertId()` function, you can extend
the `ODBCProcessor` class and override the function as follows:

```php
class CustomProcessor extends O

DBCProcessor
{
    /**
     * @param Builder $query
     * @param null $sequence
     * @return mixed
     */
    public function getLastInsertId(Builder $query, $sequence = null)
    {
        return $query->getConnection()->table($query->from)->latest('id')->first()->getAttribute($sequence);
    }
}
```
