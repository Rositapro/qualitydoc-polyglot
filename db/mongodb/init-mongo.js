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

// Insertar documentos semilla de prueba si la colección está vacía
if (db.documents.countDocuments({}) === 0) {
  db.documents.insertMany([
    {
      title: "Manual de Procedimientos ISO 9001 - Empresa 1",
      fileExtension: "pdf",
      metadata: {
        author: "Juan Pérez",
        department: "Calidad",
        version: "1.2"
      },
      tags: ["iso9001", "calidad", "procedimientos"],
      textContent: "Este documento contiene el manual de procedimientos ISO 9001 correspondiente a los estándares de calidad de la Empresa 1.",
      empresaid: 1,
      createdAt: new Date(),
      updatedAt: new Date()
    },
    {
      title: "Manual de Facturación y Ventas - Empresa 2",
      fileExtension: "pdf",
      metadata: {
        author: "María López",
        department: "Ventas",
        version: "2.0"
      },
      tags: ["facturacion", "ventas", "manual"],
      textContent: "Este es el manual técnico de facturación, caja y operaciones de ventas exclusivas para la Empresa 2.",
      empresaid: 2,
      createdAt: new Date(),
      updatedAt: new Date()
    },
    {
      title: "Minuta de Reunión - Desarrollo de Software - Empresa 1",
      fileExtension: "docx",
      metadata: {
        author: "Rosalinda",
        department: "Tecnología",
        priority: "alta"
      },
      tags: ["software", "desarrollo", "minuta"],
      textContent: "Avances del proyecto integrador de la Empresa 1. Se revisaron los endpoints de Node y MongoDB.",
      empresaid: 1,
      createdAt: new Date(),
      updatedAt: new Date()
    },
    {
      title: "Estrategia de Marketing Digital - Empresa 2",
      fileExtension: "docx",
      metadata: {
        author: "Carlos Gómez",
        department: "Marketing",
        priority: "media"
      },
      tags: ["marketing", "estrategia", "publicidad"],
      textContent: "Plan y cronograma de publicidad en redes sociales para posicionar la marca de la Empresa 2 durante el semestre actual.",
      empresaid: 2,
      createdAt: new Date(),
      updatedAt: new Date()
    }
  ]);
  print("Documentos semilla insertados exitosamente en MongoDB.");
} else {
  print("La colección 'documents' ya contiene datos. Saltando inserción de semillas.");
}
