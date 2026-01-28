# ---------- build stage ----------
FROM node:20-alpine AS build

WORKDIR /app

# install deps first (better caching)
COPY package.json package-lock.json* ./
RUN npm install

# copy source
COPY . .

# build production assets
RUN npm run build


# ---------- runtime stage ----------
FROM nginx:alpine

# remove default nginx config
RUN rm /etc/nginx/conf.d/default.conf

# custom nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

# copy build output
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
