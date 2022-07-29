# Laravel Envoy デプロイ


#### プロジェクトフォルダ構成
```
* -- /var/www/sample
          |---------- current --> /var/www/sample/releases/latestrelease
          |---------- .env
          |---------- releases
          |---------- storage
```

##### バーチャルホストの設定を更新する必要があります。
`/var/www/sample/current/public`

##### Envoy Production ファイルセットアップ
```sh
cp ./src/Envoy.blade.prod.php ./src/Envoy.blade.php
```

##### Envoy Local ファイルセットアップ
```sh
cp ./src/Envoy.blade.local.php ./src/Envoy.blade.php
```

##### 環境ファイルセットアップ
```sh
ln -nfs ./src/.env.example .env
```

##### Composerセットアップ
```sh
cd ./src && composer install
```

##### デプロイ方法
```bash
cd src && ./vendor/bin/envoy run deploy --branch=
```

---

## Initialize Project
### 🚀 初期セットアップ
```bash
./vendor/bin/envoy run init --branch=
```

## Release rollback
### ロールバックが必要な場合は、下記コマンド実行
```bash
./vendor/bin/envoy run rollback
```

---

## Services
#### サービスリロード
```bash
./vendor/bin/envoy run reload_services
```
