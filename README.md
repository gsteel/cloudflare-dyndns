# Itch, Scratch, Dynamic DNS with CloudFlare and PHP

This is a **simple** script to update IPv4 A records for domain names according to your current external IP.

There is nothing pretty about it, and if anything goes wrong, you'll just get nasty exception traces in your terminal.

### Installation

Clone the repo somewhere, cd to it and issue a composer install:

```bash
git clone git@github.com:gsteel/cloudflare-dyndns.git
cd cloudflare-dyndns
composer install
```

### Configuration

Create `config.php` by copying `config.dist.php` and editing it to match the zones you want to update. Make sure to set up an API token with CloudFlare that has the relevant privileges and access to the zones you want to modify.

### Run it…

```bash
php update-zones.php
```

You could also just use a dynamic DNS service 🤷‍♂️
