<template>
    <div v-show="currentlyIsVisible">
        <div v-for="(childField, index) in currentField.fields" :key="childField.attribute || index">
            <component
                ref="childFields"
                :is="'form-' + childField.component"
                :resource-name="resourceName"
                :resource-id="resourceId"
                :field="childField"
                :errors="errors"
                :related-resource-name="relatedResourceName"
                :related-resource-id="relatedResourceId"
                :via-resource="viaResource"
                :via-resource-id="viaResourceId"
                :via-relationship="viaRelationship"
                :show-help-text="childField.helpText != null"
            />
        </div>
    </div>
</template>

<script>

import { defineComponent } from 'vue'
import { DependentFormField, HandlesValidationErrors } from 'laravel-nova'

export default defineComponent({
    mixins: [DependentFormField, HandlesValidationErrors],

    props: [
        'field',
        'resourceId',
        'viaResource',
        'resourceName',
        'viaResourceId',
        'viaRelationship',
        'relatedResourceId',
        'relatedResourceName',
    ],

    methods: {
        setInitialValue() {
            this.value = this.currentField.value || ''
        },

        fill(formData) {
            const children = this.$refs.childFields || []
            for (const child of children) {
                if (typeof child.fill === 'function') {
                    child.fill(formData)
                }
            }
        },

        handleChange(value) {
            this.value = value
        },
    },
})

</script>
