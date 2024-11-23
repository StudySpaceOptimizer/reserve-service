docker run --name pg-dev \
  -e POSTGRES_USER=dev \
  -e POSTGRES_PASSWORD=dev \
  -e POSTGRES_DB=dev \
  -p 5432:5432 \
  -d postgres:17