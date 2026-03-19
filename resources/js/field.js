import FormField from './components/FormField.vue'

Nova.booting((app, router, store) => {
    app.component('form-json-wrapper', FormField)
})
