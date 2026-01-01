import React from 'react';
import { HashRouter as Router, Routes, Route } from 'react-router-dom';
import TemplateList from './pages/TemplateList';
import Editor from './pages/Editor';

const App: React.FC = () => {
  return (
    <Router>
      <Routes>
        <Route path="/" element={<TemplateList />} />
        <Route path="/editor/:id" element={<Editor />} />
      </Routes>
    </Router>
  );
};

export default App;
