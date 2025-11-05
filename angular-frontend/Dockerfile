FROM node:18-alpine AS build

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm install

# Copy application files
COPY . .

# Build the application
RUN npm run build -- --configuration production

# Stage 2: Serve with Nginx
FROM nginx:alpine

# Copy custom nginx config
COPY nginx.conf /etc/nginx/nginx.conf

# Copy built application from build stage
COPY --from=build /app/dist/elvin-frontend /usr/share/nginx/html

# Create env-config.js that will be injected at runtime
RUN echo "window.OTEL_EXPORTER_OTLP_ENDPOINT = 'http://jaeger:4318/v1/traces';" > /usr/share/nginx/html/env-config.js

# Expose port 80
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]