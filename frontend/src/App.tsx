import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import ProtectedRoute from './components/ProtectedRoute'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import DashboardPage from './pages/DashboardPage'
import NewSourcePage from './pages/NewSourcePage'
import SourceDetailPage from './pages/SourceDetailPage'
import NewEndpointPage from './pages/NewEndpointPage'

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <DashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources/new"
            element={
              <ProtectedRoute>
                <NewSourcePage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources/:sourceId/endpoints/new"
            element={
              <ProtectedRoute>
                <NewEndpointPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources/:sourceId"
            element={
              <ProtectedRoute>
                <SourceDetailPage />
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
