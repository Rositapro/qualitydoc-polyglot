# ==========================================
# Etapa 1: Compilación de la aplicación
# ==========================================
FROM node:20-alpine AS builder

WORKDIR /app

# Copiar configuración de dependencias
COPY package.json ./

# Instalar todas las dependencias (incluyendo devDependencies para compilar TS)
RUN npm install

# Copiar el código fuente y la configuración de TypeScript
COPY tsconfig.json ./
COPY src ./src

# Compilar TypeScript a JavaScript
RUN npm run build

# ==========================================
# Etapa 2: Imagen final para ejecución
# ==========================================
FROM node:20-alpine AS runner

WORKDIR /app

ENV NODE_ENV=production

# Copiar package.json para instalar solo dependencias de producción
COPY package.json ./

# Instalar solo dependencias requeridas en producción
RUN npm install --only=production

# Copiar los archivos compilados desde la etapa anterior
COPY --from=builder /app/dist ./dist

# Copiar la carpeta pública del frontend
COPY public ./public

# Puerto expuesto por el microservicio
EXPOSE 3000

# Comando para arrancar el servicio en producción
CMD ["node", "dist/index.js"]
