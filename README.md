# Nova Json Wrapper

[![Latest Version on Packagist](https://img.shields.io/packagist/v/codehero-mx/nova-json-wrapper)](https://packagist.org/packages/codehero-mx/nova-json-wrapper)
[![Total Downloads](https://img.shields.io/packagist/dt/codehero-mx/nova-json-wrapper)](https://packagist.org/packages/codehero-mx/nova-json-wrapper)
[![License](https://img.shields.io/packagist/l/codehero-mx/nova-json-wrapper)](https://github.com/codehero-mx/nova-json-wrapper/blob/master/LICENSE)

A Laravel Nova 5 field that groups multiple Nova fields and stores their values as a single JSON column. Supports nested wrappers, validation, `dependsOn` for dynamic fields, and works seamlessly on create, update, and detail views.

## Requirements

- PHP ^8.2
- Laravel Nova ^5.0
- Vue 3 (included with Nova 5)

## Installation

```bash
composer require codehero-mx/nova-json-wrapper
```

The service provider is auto-registered.

## Usage

### 1. Cast the JSON column on your model

```php
class User extends Model
{
    protected $casts = [
        'setting_value' => 'array',
    ];
}
```

### 2. Add `HasJsonWrapper` trait and define fields in your Nova resource

```php
use CodeheroMx\JsonWrapper\JsonWrapper;
use CodeheroMx\JsonWrapper\HasJsonWrapper;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class User extends Resource
{
    use HasJsonWrapper;

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name', 'name')->rules('required'),

            JsonWrapper::make('options', [
                Text::make('First Name', 'first_name')->rules('required'),
                Text::make('Last Name', 'last_name')->rules('required'),
                Number::make('Age', 'age')->rules('required', 'numeric', 'min:0'),
            ]),
        ];
    }
}
```

This stores the following JSON in the `options` column:

```json
{ "first_name": "John", "last_name": "Doe", "age": 30 }
```

### 3. Nested wrappers

You can nest `JsonWrapper` fields to create deep JSON structures:

```php
JsonWrapper::make('options', [
    Text::make('First Name', 'first_name')->rules('required'),
    Text::make('Last Name', 'last_name')->rules('required'),

    JsonWrapper::make('body_mass', [
        Number::make('Weight', 'weight')->rules('required'),
        Number::make('Height', 'height')->rules('required'),
    ]),
])
```

Result:

```json
{
    "first_name": "John",
    "last_name": "Doe",
    "body_mass": {
        "weight": 70,
        "height": 180
    }
}
```

### 4. Dynamic fields with `dependsOn`

`JsonWrapper` supports Nova's `dependsOn` to dynamically change the child fields based on another field's value. This is useful when the JSON structure varies depending on a selection.

In the `dependsOn` callback you receive the `JsonWrapper` instance — replace its `$field->fields` collection with the new set of fields and call `$field->show()` or `$field->hide()` as needed.

```php
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;

public function fields(NovaRequest $request): array
{
    return [
        Select::make('Role', 'role')
            ->options([
                'developer' => 'Developer',
                'designer' => 'Designer',
            ])
            ->rules('required'),

        JsonWrapper::make('profile', $this->profileFieldsFor($this->resource->role ?? ''))
            ->dependsOn(
                ['role'],
                function (JsonWrapper $field, NovaRequest $request, FormData $formData) {
                    $role = $formData->get('role');

                    $field->fields = collect(match ($role) {
                        'developer' => [
                            Select::make('Language', 'language')
                                ->options(['php' => 'PHP', 'js' => 'JavaScript', 'go' => 'Go'])
                                ->rules('required'),
                            Number::make('Years of Experience', 'experience')->rules('required', 'min:0'),
                        ],
                        'designer' => [
                            Text::make('Tool', 'tool')->rules('required'),
                            Text::make('Portfolio URL', 'portfolio_url')->rules('required', 'url'),
                        ],
                        default => [],
                    });

                    $role ? $field->show() : $field->hide();
                },
            ),
    ];
}

/**
 * Return the initial child fields for edit/detail views.
 */
private function profileFieldsFor(string $role): array
{
    return match ($role) {
        'developer' => [
            Select::make('Language', 'language')
                ->options(['php' => 'PHP', 'js' => 'JavaScript', 'go' => 'Go'])
                ->rules('required'),
            Number::make('Years of Experience', 'experience')->rules('required', 'min:0'),
        ],
        'designer' => [
            Text::make('Tool', 'tool')->rules('required'),
            Text::make('Portfolio URL', 'portfolio_url')->rules('required', 'url'),
        ],
        default => [],
    };
}
```

When the user selects a role, the wrapper swaps its child fields accordingly. On edit, existing JSON values are automatically resolved into the fields.

> **Tip:** The initial fields passed to `JsonWrapper::make()` are used when the resource already exists (edit/detail). The `dependsOn` callback fires on every form sync and replaces them dynamically.

### 5. Showing fields on the index view

By default, child fields are hidden on the index/list view. Use `indexFields()` to choose which ones to display:

```php
JsonWrapper::make('options', [
    Text::make('First Name', 'first_name')->rules('required'),
    Text::make('Last Name', 'last_name')->rules('required'),
    Number::make('Age', 'age')->rules('required', 'numeric', 'min:0'),
])->indexFields(['first_name', 'age'])
```

Only `First Name` and `Age` will appear as columns on the resource index. The fields are resolved from the JSON column automatically.

> **Note:** Index fields are **read-only display columns**. Since the data lives inside a JSON column, they cannot be sorted, filtered, or searched through Nova's built-in mechanisms. They also only work with first-level child fields (not nested wrappers).

## How it works

- **`JsonWrapper`** extends `Laravel\Nova\Fields\Field` with the `SupportsDependentFields` trait. It manages a collection of child fields that are resolved, filled, and validated against the JSON column.
- **`HasJsonWrapper`** is a trait for your Nova Resource that ensures the wrapper is included during form operations (create, update, sync) and flattens child fields on the detail view so they display individually.
- On the **frontend**, the Vue 3 component uses Nova's `DependentFormField` mixin to handle `dependsOn` synchronization, visibility toggling, and delegating `fill()` to rendered child component instances.

## Notes

- There are no visual indications that the fields are wrapped in JSON — they appear as normal Nova fields. This is intentional.
- The `HasJsonWrapper` trait is **required** on any resource that uses `JsonWrapper`. It handles field visibility across different Nova controllers (create, update, detail, index, sync).
- Validation rules on child fields work exactly like regular Nova fields (`->rules('required', 'numeric')`, etc.).

## Credits

This package is based on [nova-json-wrapper](https://github.com/dcasia/nova-json-wrapper) by [Digital Creative](https://github.com/dcasia), originally licensed under MIT. It has been upgraded and adapted for Laravel Nova 5.

## License

The MIT License (MIT). Please see [License File](https://raw.githubusercontent.com/codehero-mx/nova-json-wrapper/master/LICENSE) for more information.
