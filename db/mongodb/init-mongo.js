// Script de inicialización de colecciones, índices y datos semilla para MongoDB
// Módulo de Indexación y Búsqueda

db = db.getSiblingDB('qualitydoc');

// Crear índice de texto compuesto
db.documents.createIndex(
  {
    title: "text",
    tags: "text",
    textContent: "text"
  },
  {
    weights: {
      title: 10,
      tags: 5,
      textContent: 1
    },
    name: "DocumentTextIndex",
    default_language: "spanish"
  }
);

// No se insertan documentos de prueba para iniciar con la colección vacía
print("Iniciando con la colección 'documents' vacía.");
