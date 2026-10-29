import '../css/main.css';

declare global {
    interface Window { GlitterLearning?: { version: string; eventNamespace: string } }
}

window.GlitterLearning = Object.freeze({version: '1.0.0', eventNamespace: 'glitter:learning:'});
