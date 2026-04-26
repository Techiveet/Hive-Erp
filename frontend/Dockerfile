FROM node:20-alpine
WORKDIR /app
COPY package*.json ./
# 🚀 Bypass the React 19 version conflict
RUN npm ci --legacy-peer-deps
COPY . .
EXPOSE 3000
