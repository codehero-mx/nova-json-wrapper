<?php

namespace CodeheroMx\JsonWrapper;

use Illuminate\Support\Collection;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Controllers\CreationFieldController;
use Laravel\Nova\Http\Controllers\CreationFieldSyncController;
use Laravel\Nova\Http\Controllers\CreationPivotFieldController;
use Laravel\Nova\Http\Controllers\ResourceShowController;
use Laravel\Nova\Http\Controllers\ResourceStoreController;
use Laravel\Nova\Http\Controllers\ResourceUpdateController;
use Laravel\Nova\Http\Controllers\UpdateFieldController;
use Laravel\Nova\Http\Controllers\UpdatePivotFieldController;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * Trait for Nova Resources that use JsonWrapper fields.
 *
 * Handles controller-aware field visibility: returns the full wrapper for
 * form controllers (create/update/sync), flattens child fields for the
 * detail view, and hides wrappers on index/other controllers.
 */
trait HasJsonWrapper
{
    public function availablePanelsForDetail(NovaRequest $request, Resource $resource, FieldCollection $fields): array
    {
        return parent::availablePanelsForDetail($request, $resource, $fields);
    }

    /**
     * Return fields appropriate for the current controller context.
     *
     * - Store / Update / Field-sync controllers → full fields (wrapper intact)
     * - Detail (ResourceShowController) → wrapper children flattened to top level
     * - Index / everything else → JsonWrapper fields removed
     */
    public function availableFields(NovaRequest $request): FieldCollection
    {
        $controller = $request->route()?->controller;

        if ($controller instanceof ResourceStoreController ||
            $controller instanceof ResourceUpdateController ||
            $controller instanceof CreationFieldController ||
            $controller instanceof CreationFieldSyncController ||
            $controller instanceof CreationPivotFieldController ||
            $controller instanceof UpdateFieldController ||
            $controller instanceof UpdatePivotFieldController) {
            return parent::availableFields($request);
        }

        if ($controller instanceof ResourceShowController) {
            return $this->flattenFields(parent::availableFields($request));
        }

        return parent::availableFields($request)->flatMap(function ($field) {
            if ($field instanceof JsonWrapper) {
                return $field->getIndexFields();
            }

            return [$field];
        });
    }

    /**
     * Recursively flatten JsonWrapper child fields for the detail view,
     * rewriting each child's attribute to a "parent->child" accessor path.
     */
    private function flattenFields(Collection $fields): Collection
    {
        if ($fields->whereInstanceOf(JsonWrapper::class)->isEmpty()) {
            return $fields;
        }

        return $fields->flatMap(function ($field) {
            if ($field instanceof JsonWrapper) {
                foreach ($field->fields as $child) {
                    $child->panel = $field->panel;
                    $child->attribute = "$field->attribute->$child->attribute";
                }

                return $this->flattenFields($field->fields);
            }

            return [$field];
        });
    }
}
