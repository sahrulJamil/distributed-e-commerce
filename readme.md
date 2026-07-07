````md
# 🛒 Distributed E-Commerce API

> Sistem e-commerce produk koleksi dan merchandise hobi berbasis Laravel dengan implementasi **basis data terdistribusi**, **PostgreSQL replication**, **Redis cache**, dan **MongoDB activity log**.

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?style=for-the-badge&logo=redis&logoColor=white)
![MongoDB](https://img.shields.io/badge/MongoDB-7-47A248?style=for-the-badge&logo=mongodb&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-Stream-009639?style=for-the-badge&logo=nginx&logoColor=white)

---

## 📌 Tentang Proyek

**Distributed E-Commerce API** adalah backend sistem e-commerce untuk produk koleksi dan merchandise hobi, seperti vinyl, action figure, figurine, dan barang koleksi lainnya.

Selain menyediakan fitur e-commerce umum seperti autentikasi, produk, keranjang, checkout, dan riwayat transaksi, proyek ini menerapkan konsep basis data terdistribusi melalui:

- Fragmentasi data berdasarkan domain pengguna, produk, dan transaksi.
- PostgreSQL primary-replica untuk data produk.
- Redis sebagai cache data katalog produk.
- MongoDB untuk pencatatan activity log.
- Nginx Stream Proxy sebagai jalur komunikasi TCP menuju database service.

---

## ✨ Fitur Utama

### Pengguna

- Registrasi pengguna.
- Login, logout, dan autentikasi token.
- Pengelolaan alamat pengiriman.
- Melihat daftar serta detail produk.
- Menambahkan produk ke keranjang.
- Mengubah jumlah atau menghapus item keranjang.
- Checkout menggunakan alamat pengiriman.
- Melihat riwayat dan detail transaksi.

### Katalog Produk

- CRUD kategori produk.
- CRUD produk.
- Upload dan hapus gambar produk.
- Penentuan gambar utama produk.
- Validasi stok dan status produk aktif.

### Basis Data Terdistribusi

- Pemisahan database berdasarkan fungsi.
- PostgreSQL streaming replication untuk katalog produk.
- Read/write split pada data produk.
- Redis cache untuk daftar dan detail produk.
- Cache invalidation setelah produk, gambar, atau stok berubah.
- MongoDB activity log untuk login dan checkout.
- Nginx Stream Proxy untuk PostgreSQL, Redis, dan MongoDB.

---

## 🏗️ Arsitektur Sistem

```mermaid
flowchart TD
    Client["Postman / Client"] --> NginxApp["Nginx App :8080"]
    NginxApp --> Laravel["Laravel API"]

    Laravel --> NginxDB["Nginx DB Stream Proxy"]

    NginxDB --> UserDB["PostgreSQL<br/>user_db"]
    NginxDB --> ProductPrimary["PostgreSQL<br/>product_db primary"]
    NginxDB --> ProductReplica["PostgreSQL<br/>product_db replica"]
    NginxDB --> TransactionDB["PostgreSQL<br/>transaction_db"]
    NginxDB --> Redis["Redis Cache"]
    NginxDB --> Mongo["MongoDB<br/>ecommerce_logs"]

    ProductPrimary -->|"Streaming Replication"| ProductReplica
````

---

## 🗂️ Fragmentasi Data

| Database / Service | Fungsi                             | Data Utama                                                   |
| ------------------ | ---------------------------------- | ------------------------------------------------------------ |
| `user_db`          | Identitas dan autentikasi pengguna | `users`, `addresses`, `personal_access_tokens`               |
| `product_db`       | Katalog dan stok produk            | `categories`, `products`, `product_images`                   |
| `transaction_db`   | Keranjang dan transaksi            | `carts`, `cart_items`, `transactions`, `transaction_details` |
| `ecommerce_logs`   | Activity log fleksibel             | `activity_logs`                                              |
| Redis              | Cache katalog produk               | `products:index`, `products:show:{id}`                       |

---

## 🔄 Mekanisme Read dan Write Produk

```text
Write Product / Checkout
        ↓
Product Primary
        ↓
PostgreSQL Streaming Replication
        ↓
Product Replica

GET /products
        ↓
Redis Cache
        ↓ cache miss
Product Replica
```

* Operasi **create, update, delete**, serta pengurangan stok dilakukan pada **product primary**.
* Operasi baca produk dilakukan dari **Redis cache**.
* Jika cache belum tersedia, data dibaca dari **product replica**.
* Redis cache akan dihapus ketika produk, gambar produk, atau stok berubah.

---

## 📦 Teknologi yang Digunakan

| Teknologi                        | Kegunaan                                     |
| -------------------------------- | -------------------------------------------- |
| Laravel                          | Backend dan application layer                |
| PostgreSQL                       | Database relasional utama                    |
| PostgreSQL Streaming Replication | Replikasi product primary ke product replica |
| Redis                            | Cache daftar dan detail produk               |
| MongoDB                          | Activity log login dan checkout              |
| Nginx HTTP                       | Reverse proxy untuk Laravel                  |
| Nginx Stream                     | TCP proxy untuk database service             |
| Docker Compose                   | Container orchestration                      |
| Laravel Sanctum                  | Token authentication                         |
| Postman                          | Pengujian REST API                           |

---

## 🚀 Menjalankan Project

### 1. Clone repository

```bash
git clone <repository-url>
cd distributed-base-commerce
```

### 2. Salin konfigurasi environment

```bash
cp laravel-api/.env.example laravel-api/.env
```

Sesuaikan nilai environment jika diperlukan.

### 3. Jalankan seluruh container

```bash
docker compose up -d --build
```

### 4. Generate application key

```bash
docker compose exec app php artisan key:generate
```

### 5. Jalankan migration

```bash
docker compose exec app php artisan migrate \
  --database=user_db \
  --path=database/migrations/user
```

```bash
docker compose exec app php artisan migrate \
  --database=product_db \
  --path=database/migrations/product
```

```bash
docker compose exec app php artisan migrate \
  --database=transaction_db \
  --path=database/migrations/transaction
```

### 6. Buat symbolic link storage

```bash
docker compose exec app php artisan storage:link
```

Aplikasi dapat diakses melalui:

```text
http://localhost:8080
```

---

## 🔌 Service dan Port

| Service                   |       Host / Port | Keterangan                   |
| ------------------------- | ----------------: | ---------------------------- |
| Laravel melalui Nginx App |  `localhost:8080` | REST API                     |
| User Database             | `localhost:15432` | PostgreSQL `user_db`         |
| Product Primary           | `localhost:15433` | PostgreSQL write node        |
| Product Replica           | `localhost:15434` | PostgreSQL read node         |
| Transaction Database      | `localhost:15435` | PostgreSQL `transaction_db`  |
| Redis                     | `localhost:16379` | Redis melalui Nginx Stream   |
| MongoDB                   | `localhost:17017` | MongoDB melalui Nginx Stream |

---

## 🔐 Authentication

Sistem menggunakan Laravel Sanctum dengan Bearer Token.

Setelah login berhasil, masukkan token pada header request:

```text
Authorization: Bearer <token>
```

Contoh login:

```http
POST /api/login
```

```json
{
  "email": "user@example.com",
  "password": "password"
}
```

---

## 📡 Endpoint Utama

### Authentication

| Method | Endpoint        | Deskripsi                     |
| ------ | --------------- | ----------------------------- |
| `POST` | `/api/register` | Registrasi pengguna           |
| `POST` | `/api/login`    | Login pengguna                |
| `POST` | `/api/logout`   | Logout pengguna               |
| `GET`  | `/api/me`       | Mengambil data pengguna login |

### Address

| Method   | Endpoint              | Deskripsi              |
| -------- | --------------------- | ---------------------- |
| `GET`    | `/api/addresses`      | Daftar alamat pengguna |
| `POST`   | `/api/addresses`      | Tambah alamat          |
| `PUT`    | `/api/addresses/{id}` | Ubah alamat            |
| `DELETE` | `/api/addresses/{id}` | Hapus alamat           |

### Category

| Method   | Endpoint               | Deskripsi       |
| -------- | ---------------------- | --------------- |
| `GET`    | `/api/categories`      | Daftar kategori |
| `POST`   | `/api/categories`      | Tambah kategori |
| `PUT`    | `/api/categories/{id}` | Ubah kategori   |
| `DELETE` | `/api/categories/{id}` | Hapus kategori  |

### Product

| Method   | Endpoint             | Deskripsi     |
| -------- | -------------------- | ------------- |
| `GET`    | `/api/products`      | Daftar produk |
| `GET`    | `/api/products/{id}` | Detail produk |
| `POST`   | `/api/products`      | Tambah produk |
| `PUT`    | `/api/products/{id}` | Ubah produk   |
| `DELETE` | `/api/products/{id}` | Hapus produk  |

### Product Image

| Method   | Endpoint                                 | Deskripsi            |
| -------- | ---------------------------------------- | -------------------- |
| `POST`   | `/api/products/{id}/images`              | Upload gambar produk |
| `DELETE` | `/api/products/{product}/images/{image}` | Hapus gambar produk  |

### Cart

| Method   | Endpoint               | Deskripsi           |
| -------- | ---------------------- | ------------------- |
| `GET`    | `/api/cart`            | Melihat cart aktif  |
| `POST`   | `/api/cart/items`      | Tambah item ke cart |
| `PUT`    | `/api/cart/items/{id}` | Ubah jumlah item    |
| `DELETE` | `/api/cart/items/{id}` | Hapus item cart     |

### Transaction

| Method | Endpoint                 | Deskripsi                   |
| ------ | ------------------------ | --------------------------- |
| `POST` | `/api/checkout`          | Membuat transaksi dari cart |
| `GET`  | `/api/transactions`      | Riwayat transaksi           |
| `GET`  | `/api/transactions/{id}` | Detail transaksi            |

---

## ⚡ Demonstrasi Redis Cache

Endpoint produk menampilkan header untuk menunjukkan sumber data.

Request pertama:

```text
GET /api/products
```

Response header:

```text
X-Data-Source: database
X-Database-Source: product-replica
```

Request berikutnya:

```text
X-Data-Source: redis-cache
X-Database-Source: not queried
```

Hal ini menunjukkan bahwa request pertama membaca product replica, sedangkan request berikutnya mengambil data dari Redis cache.

---

## 📜 Demonstrasi MongoDB Activity Log

Activity log disimpan dalam database MongoDB:

```text
Database: ecommerce_logs
Collection: activity_logs
```

Contoh aktivitas yang tercatat:

```json
{
  "user_id": 1,
  "action": "checkout_created",
  "description": "User berhasil melakukan checkout",
  "metadata": {
    "transaction_id": 2,
    "invoice_number": "INV-20260701123052-ZVCV2",
    "total_price": "650000.00",
    "items_count": 1
  }
}
```

Activity log juga dapat menyimpan metadata tambahan seperti alamat IP dan user agent.

---

## 🧪 Pengujian Replikasi Produk

Tambahkan atau ubah produk melalui API. Data akan ditulis ke product primary.

Kemudian cek data melalui product replica:

```bash
docker compose exec postgres-product-replica \
  psql -U admin -d product_db
```

Contoh query:

```sql
SELECT id, name, stock
FROM products;
```

Jika data perubahan dari primary terlihat pada replica, maka PostgreSQL streaming replication berjalan dengan baik.

---

## 📁 Struktur Project

```text
distributed-base-commerce/
├── docker-compose.yml
├── laravel-api/
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   └── Services/
│   ├── config/
│   ├── database/
│   │   └── migrations/
│   │       ├── user/
│   │       ├── product/
│   │       └── transaction/
│   └── routes/
│       └── api.php
├── nginx/
│   ├── app.conf
│   └── db.conf
└── postgres/
    └── replica-entrypoint.sh
```

---

## 📝 Catatan

* Product replica bersifat read-only.
* Cross-database relation tidak menggunakan foreign key fisik.
* Validasi relasi antarfragment dilakukan melalui application layer.
* Checkout menggunakan mekanisme kompensasi sederhana untuk mengembalikan stok apabila pembuatan transaksi gagal setelah stok dikurangi.
* Activity log bersifat pendukung dan tidak membatalkan transaksi utama apabila proses pencatatan log gagal.

---

## 👨‍💻 Author

**Sahrul Jamil**
Informatika — Sekolah Tinggi Teknologi Cipasung

---

<p align="center">
  Dibangun untuk pembelajaran implementasi basis data terdistribusi pada sistem e-commerce.
</p>
```
