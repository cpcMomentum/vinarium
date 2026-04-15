import { createApp } from 'vue'
import App from './App.vue'

// Wait until the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const app = createApp(App)
    // Mount to the container from templates/index.php
    app.mount('.app-vinarium')
})
