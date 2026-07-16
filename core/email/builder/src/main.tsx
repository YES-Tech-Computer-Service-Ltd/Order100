import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'

import ErrorBoundary from './ErrorBoundary'

const rootElement = document.getElementById('o100ne-main-pages');
if (rootElement) {
  ReactDOM.createRoot(rootElement).render(
    <React.StrictMode>
      <ErrorBoundary>
        <App />
      </ErrorBoundary>
    </React.StrictMode>,
  )
}
