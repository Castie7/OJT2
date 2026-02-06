import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'

// Create the app instance
const app = createApp(App)

app.use(router)

// Mount the app to the #app div in your HTML
app.mount('#app')