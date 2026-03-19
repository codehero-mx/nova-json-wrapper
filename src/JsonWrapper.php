<?php

namespace CodeheroMx\JsonWrapper;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\SupportsDependentFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A Nova field that groups multiple child fields and persists their values
 * as a single JSON column on the model.
 *
 * Supports nesting (JsonWrapper inside JsonWrapper), dependsOn for dynamic
 * field swapping, and standard Nova validation rules on each child field.
 */
class JsonWrapper extends Field
{
    use SupportsDependentFields;

    public $component = 'json-wrapper';

    /** @var Collection<int, Field> The child fields managed by this wrapper. */
    public Collection $fields;

    /** @var mixed The resource instance stored during resolve(), used to re-resolve after dependsOn sync. */
    private mixed $resolvedResource = null;

    /**
     * @param string $attribute The JSON column name on the model.
     * @param Field[] $fields    Child fields whose values are stored inside the JSON column.
     */
    public function __construct(string $attribute, array $fields = [])
    {
        parent::__construct($attribute, $attribute);

        $this->fields = collect($fields);
    }

    /**
     * Resolve the field's value from the given resource, recursively resolving
     * all child fields using the "->" accessor syntax.
     */
    public function resolve($resource, ?string $attribute = null): void
    {
        $this->resolvedResource = $resource;
        $this->recursiveResolve($resource, $this->fields, collect($attribute ?? $this->attribute));
    }

    /**
     * After a dependsOn callback replaces child fields, re-resolve them with
     * the stored resource so existing values are preserved on edit views.
     */
    public function syncDependsOn(NovaRequest $request)
    {
        $result = parent::syncDependsOn($request);

        if ($this->resolvedResource !== null) {
            $this->recursiveResolve($this->resolvedResource, $this->fields, collect($this->attribute));
        }

        return $result;
    }

    /**
     * Walk nested fields and resolve each one against the resource using an
     * accumulated "parent->child" attribute path.
     */
    private function recursiveResolve($resource, Collection $fields, Collection $bag): void
    {
        foreach ($fields as $field) {
            if ($field instanceof JsonWrapper) {
                $this->recursiveResolve($resource, $field->fields, $bag->merge($field->attribute));
                continue;
            }

            $field->resolve($resource, $bag->merge($field->attribute)->join('->'));
        }
    }

    /**
     * Aggregate validation rules from all child fields.
     */
    public function getRules(NovaRequest $request): array
    {
        $rules = parent::getRules($request);

        foreach ($this->fields as $field) {
            $rules = array_merge($rules, $field->getRules($request));
        }

        return $rules;
    }

    /**
     * Fill the model's JSON column by delegating to each child field.
     *
     * Uses newInstance() to build a clean model clone that only accumulates
     * the child field values, avoiding leaking parent model attributes.
     */
    public function fill(NovaRequest $request, $model)
    {
        $clone = $model->newInstance();
        $callbacks = [];

        if ($model->exists) {
            $originalValues = json_decode($model->getRawOriginal($this->attribute), true) ?? [];

            $clone->setRawAttributes(
                collect($originalValues)->only($this->fields->map->attribute->filter())->toArray()
            );
        }

        foreach ($this->fields->whereInstanceOf(JsonWrapper::class) as $field) {
            $subClone = $clone->newInstance();
            $callbacks[] = $field->fill($request, $subClone);

            foreach ($subClone->toArray() as $key => $data) {
                $clone->setAttribute($key, $data);
            }
        }

        $nonJsonWrapperFields = $this->fields->reject(fn ($field) => $field instanceof JsonWrapper);

        foreach ($nonJsonWrapperFields as $field) {
            $callbacks[] = $field->fill($request, $clone);
        }

        $model->setAttribute($this->attribute, $clone->attributesToArray());

        $callbacks[] = parent::fill($request, $model);

        return function () use ($callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback);
                }
            }
        };
    }

    /**
     * Serialize the field for the frontend, including child fields.
     *
     * Re-resolves child fields to ensure values are present even after
     * dependsOn callbacks that replace the fields collection.
     */
    public function jsonSerialize(): array
    {
        if ($this->resolvedResource !== null) {
            $this->recursiveResolve($this->resolvedResource, $this->fields, collect($this->attribute));
        }

        return array_merge(['fields' => $this->fields], parent::jsonSerialize());
    }
}
