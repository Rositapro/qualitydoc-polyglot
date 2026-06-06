import { Schema, model, Document } from 'mongoose';

export interface IDocument extends Document {
  title: string;
  fileExtension: string;
  metadata: Record<string, any>;
  tags: string[];
  textContent: string;
  empresaid: number;
  createdAt: Date;
  updatedAt: Date;
}

const DocumentSchema = new Schema<IDocument>(
  {
    title: {
      type: String,
      required: [true, 'El título del documento es obligatorio'],
      trim: true,
    },
    fileExtension: {
      type: String,
      required: [true, 'La extensión del archivo es obligatoria'],
      trim: true,
      lowercase: true,
    },
    metadata: {
      type: Schema.Types.Mixed,
      default: {},
    },
    tags: {
      type: [String],
      default: [],
    },
    textContent: {
      type: String,
      required: [true, 'El contenido de texto es obligatorio'],
    },
    empresaid: {
      type: Number,
      default: 1,
    },
  },
  {
    timestamps: true, // Agrega automáticamente createdAt y updatedAt
  }
);

// Aplicar un 'Text Index' compuesto sobre el título, las etiquetas y el contenido en texto.
// Los pesos (weights) determinan la relevancia de cada campo al calcular el textScore.
DocumentSchema.index(
  {
    title: 'text',
    tags: 'text',
    textContent: 'text',
  },
  {
    weights: {
      title: 10,
      tags: 5,
      textContent: 1,
    },
    name: 'DocumentTextIndex',
    default_language: 'spanish', // Útil ya que el proyecto QualityDoc está en español
  }
);

export const DocumentModel = model<IDocument>('Document', DocumentSchema);
