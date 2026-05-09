# Guia de Boas Praticas — VPS Debian para Marketplace Mistico

> Este documento descreve o passo a passo para configurar uma VPS Debian com seguranca e subir todos os servicos do Marketplace Mistico usando Docker.

---

## Indice

1. [Arquitetura Geral](#1-arquitetura-geral)
2. [Requisitos Minimos da VPS](#2-requisitos-minimos-da-vps)
3. [Configuracao Inicial do Servidor](#3-configuracao-inicial-do-servidor)
4. [Hardening de Seguranca](#4-hardening-de-seguranca)
5. [Instalacao do Docker](#5-instalacao-do-docker)
6. [Deploy da Aplicacao](#6-deploy-da-aplicacao)
7. [SSL / HTTPS com Let's Encrypt](#7-ssl--https-com-lets-encrypt)
8. [DNS e Cloudflare](#8-dns-e-cloudflare)
9. [Backup Automatizado](#9-backup-automatizado)
10. [Monitoramento e Logs](#10-monitoramento-e-logs)
11. [CI/CD Automatizado](#11-cicd-automatizado)
12. [Manutencao e Atualizacoes](#12-manutencao-e-atualizacoes)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. Arquitetura Geral

```
                     Internet
                        |
              [Cloudflare DNS/CDN]
               /                \
  quintalmistico.com.br    api.quintalmistico.com.br
   (Cloudflare Pages)             |
   Frontend (HTML/CSS/JS)   [VPS Debian]
                                  |
                           ┌──────────────┐
                           │    Nginx      │  :80 / :443
                           │ (reverse proxy)│
                           └──────┬───────┘
                                  │
                           ┌──────────────┐
                           │   Backend    │  :8080 (internal)
                           │  (Node.js)   │
                           └──────┬───────┘
                                  │
                           ┌──────────────┐
                           │  PostgreSQL  │  :5432 (internal)
                           │   (Docker)   │
                           └──────────────┘
```

**Servicos no Docker:**
| Container       | Imagem              | Porta Publica | Porta Interna |
|-----------------|---------------------|---------------|---------------|
| `qm-nginx`      | nginx:1.27-alpine   | 80, 443       | 80, 443       |
| `qm-backend`    | build local         | —             | 8080          |
| `qm-postgres`   | postgres:16-alpine  | —             | 5432          |
| `qm-certbot`    | certbot/certbot     | —             | —             |

> **Importante:** Apenas o Nginx expoe portas publicas. Backend e PostgreSQL so sao acessiveis pela rede interna do Docker.

---

## 2. Requisitos Minimos da VPS

| Recurso | Minimo     | Recomendado  |
|---------|------------|--------------|
| CPU     | 1 vCPU     | 2 vCPUs      |
| RAM     | 2 GB       | 4 GB         |
| Disco   | 20 GB SSD  | 40 GB SSD    |
| OS      | Debian 12  | Debian 12    |
| Rede    | IPv4 fixo  | IPv4 + IPv6  |

**Provedores recomendados no Brasil:** Hostinger, Contabo, DigitalOcean, Vultr, Linode.

---

## 3. Configuracao Inicial do Servidor

### 3.1. Primeiro acesso como root

```bash
ssh root@SEU_IP_VPS
```

### 3.2. Atualizar o sistema

```bash
apt update && apt upgrade -y
apt install -y curl wget git unzip htop ufw fail2ban \
  apt-transport-https ca-certificates gnupg lsb-release
```

### 3.3. Criar usuario de deploy (nao usar root)

```bash
adduser deploy
usermod -aG sudo deploy

# Copiar chave SSH para o novo usuario
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

### 3.4. Configurar hostname e timezone

```bash
hostnamectl set-hostname vps-quintalmistico
timedatectl set-timezone America/Sao_Paulo
```

A partir daqui, faça login como `deploy`:

```bash
ssh deploy@SEU_IP_VPS
```

---

## 4. Hardening de Seguranca

### 4.1. SSH Seguro

Edite `/etc/ssh/sshd_config`:

```bash
sudo nano /etc/ssh/sshd_config
```

Altere as seguintes linhas:

```
Port 2222                      # Mudar porta padrao (evitar bots)
PermitRootLogin no             # Desabilitar login root
PasswordAuthentication no      # Apenas chave SSH
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
AllowUsers deploy
```

```bash
sudo systemctl restart sshd
```

> **ATENCAO:** Antes de fechar a sessao atual, abra uma nova sessao SSH na porta 2222 para confirmar que funciona!

### 4.2. Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing

# SSH (porta customizada)
sudo ufw allow 2222/tcp

# HTTP e HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Ativar
sudo ufw enable
sudo ufw status verbose
```

### 4.3. Fail2Ban (protecao contra brute force)

```bash
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local
```

Adicione/ajuste no final:

```ini
[sshd]
enabled  = true
port     = 2222
filter   = sshd
logpath  = /var/log/auth.log
maxretry = 3
bantime  = 3600
findtime = 600

[nginx-http-auth]
enabled  = true
port     = http,https
filter   = nginx-http-auth
logpath  = /var/log/nginx/error.log
maxretry = 5
bantime  = 1800
```

```bash
sudo systemctl enable fail2ban
sudo systemctl restart fail2ban
```

### 4.4. Atualizacoes automaticas de seguranca

```bash
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

### 4.5. Swap (para VPS com pouca RAM)

Se a VPS tem 2 GB RAM, crie um swap de 2 GB:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Otimizar swappiness
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

---

## 5. Instalacao do Docker

### 5.1. Instalar Docker Engine

```bash
# Adicionar repositorio oficial
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | \
  sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/debian \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

### 5.2. Permitir usuario deploy usar Docker

```bash
sudo usermod -aG docker deploy
# Relogar para aplicar o grupo
exit
ssh deploy@SEU_IP_VPS -p 2222
```

### 5.3. Verificar instalacao

```bash
docker --version
docker compose version
docker run hello-world
```

---

## 6. Deploy da Aplicacao

### 6.1. Clonar o repositorio

```bash
sudo mkdir -p /opt/marketplace
sudo chown deploy:deploy /opt/marketplace

git clone https://github.com/Linha-dev/Marketplacemistico.git /opt/marketplace
cd /opt/marketplace
```

### 6.2. Configurar variaveis de ambiente

```bash
cp .env.exemplo .env
nano .env
```

Preencha **obrigatoriamente:**

```bash
POSTGRES_PASSWORD=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -hex 32)
```

Cole os valores gerados no `.env`. Para gerar e copiar diretamente:

```bash
echo "POSTGRES_PASSWORD=$(openssl rand -base64 32)"
echo "JWT_SECRET=$(openssl rand -hex 32)"
```

### 6.3. Subir os servicos

```bash
# Primeiro deploy completo
./infrastructure/scripts/deploy.sh all
```

### 6.4. Verificar se tudo esta rodando

```bash
docker compose ps
docker compose logs backend --tail=20
curl -s http://localhost:8080/api/health | python3 -m json.tool
```

### 6.5. Rodar as migracoes (se nao rodaram automaticamente)

```bash
docker compose exec backend node scripts/migrate.js up
```

---

## 7. SSL / HTTPS com Let's Encrypt

### 7.1. Antes de iniciar

- Certifique-se de que o DNS do dominio da API (`api.quintalmistico.com.br`) aponta para o IP da VPS
- As portas 80 e 443 devem estar abertas no firewall

### 7.2. Obter certificado

```bash
cd /opt/marketplace
./infrastructure/scripts/ssl-init.sh api.quintalmistico.com.br
```

### 7.3. Renovacao automatica

O container `qm-certbot` ja cuida da renovacao automatica a cada 12 horas. Para forcar renovacao manual:

```bash
docker compose run --rm certbot renew --force-renewal
docker compose exec nginx nginx -s reload
```

---

## 8. DNS e Cloudflare

### 8.1. Configuracao no Cloudflare

| Tipo    | Nome                       | Valor          | Proxy    |
|---------|----------------------------|----------------|----------|
| A       | `quintalmistico.com.br`    | Cloudflare Pages | Orange   |
| CNAME   | `www`                      | Cloudflare Pages | Orange   |
| A       | `api`                      | IP_DA_VPS      | DNS only |

> **IMPORTANTE:** O registro `api` deve estar com **proxy desligado** (DNS only / cinza) para que o SSL do Let's Encrypt funcione na VPS. Caso queira usar o proxy da Cloudflare na API, use os certificados de origem da Cloudflare em vez do Let's Encrypt.

### 8.2. Frontend no Cloudflare Pages

O frontend continua hospedado no Cloudflare Pages. No painel:

1. Vá em **Workers & Pages** > **Create application** > **Pages**
2. Conecte o repositorio GitHub
3. Defina:
   - **Build command:** (vazio, pois e HTML estatico)
   - **Build output directory:** `public`
4. Defina o dominio customizado `quintalmistico.com.br`

### 8.3. CORS no Backend

Garanta que `ALLOWED_ORIGIN` no `.env` aponta para o dominio do frontend:

```
ALLOWED_ORIGIN=https://quintalmistico.com.br
FRONTEND_URL=https://quintalmistico.com.br
```

---

## 9. Backup Automatizado

### 9.1. Configurar cron para backup diario

```bash
# Criar diretorio de backups
mkdir -p /opt/marketplace/infrastructure/backups

# Adicionar ao crontab
crontab -e
```

Adicione a linha:

```
0 3 * * * /opt/marketplace/infrastructure/scripts/backup-db.sh >> /var/log/qm-backup.log 2>&1
```

Isso executa o backup todo dia as 3h da manha.

### 9.2. Backup manual

```bash
cd /opt/marketplace
./infrastructure/scripts/backup-db.sh
```

### 9.3. Restaurar um backup

```bash
gunzip < infrastructure/backups/quintalmistico_20260509_030000.sql.gz | \
  docker compose exec -T postgres psql -U qm_app -d quintalmistico
```

### 9.4. Backup externo (recomendado)

Para maior seguranca, copie backups para um storage externo:

```bash
# Exemplo com rclone para S3/Backblaze/Google Drive
sudo apt install -y rclone
rclone config  # configurar remote

# Adicionar ao crontab apos o backup local
30 3 * * * rclone copy /opt/marketplace/infrastructure/backups remote:qm-backups --max-age 7d
```

---

## 10. Monitoramento e Logs

### 10.1. Ver logs dos containers

```bash
cd /opt/marketplace

# Todos os logs
docker compose logs -f --tail=50

# Apenas backend
docker compose logs -f backend --tail=50

# Apenas postgres
docker compose logs -f postgres --tail=50

# Apenas nginx
docker compose logs -f nginx --tail=50
```

### 10.2. Recursos do sistema

```bash
# Uso geral
htop

# Disco
df -h

# Uso dos containers
docker stats

# Status dos servicos
./infrastructure/scripts/deploy.sh status
```

### 10.3. Health check do backend

```bash
curl -s http://localhost:8080/api/health | python3 -m json.tool
```

### 10.4. Alerta simples com cron

Crie um script de monitoramento basico:

```bash
cat > /opt/marketplace/infrastructure/scripts/health-monitor.sh << 'EOF'
#!/usr/bin/env bash
set -euo pipefail

HEALTH_URL="http://localhost:8080/api/health"
ALERT_EMAIL="miwoadm@gmail.com"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL" || echo "000")

if [ "$HTTP_CODE" != "200" ]; then
  echo "[$(date)] ALERT: Health check failed (HTTP $HTTP_CODE)" >> /var/log/qm-health.log
  # Opcional: enviar email
  # echo "Backend down (HTTP $HTTP_CODE)" | mail -s "QM ALERT" "$ALERT_EMAIL"

  # Tentar restart automatico
  cd /opt/marketplace
  docker compose restart backend
fi
EOF

chmod +x /opt/marketplace/infrastructure/scripts/health-monitor.sh
```

Adicionar ao crontab (verificacao a cada 5 min):

```
*/5 * * * * /opt/marketplace/infrastructure/scripts/health-monitor.sh
```

### 10.5. Rotacao de logs do Docker

Os logs ja estao configurados com limite no `docker-compose.yml`. Para verificar o tamanho:

```bash
sudo du -sh /var/lib/docker/containers/*/
```

---

## 11. CI/CD Automatizado

O workflow do GitHub Actions (`ci.yml`) faz o deploy automatico para a VPS quando um push chega na branch `main`.

### 11.1. Secrets necessarios no GitHub

Va em **Settings > Secrets and variables > Actions** no repositorio e adicione:

| Secret          | Descricao                                 |
|-----------------|-------------------------------------------|
| `VPS_HOST`      | IP ou dominio da VPS                      |
| `VPS_USER`      | Usuario de deploy (ex: `deploy`)          |
| `VPS_SSH_KEY`   | Chave SSH privada do usuario de deploy    |
| `VPS_SSH_PORT`  | Porta SSH (ex: `2222`)                    |

### 11.2. Gerar chave SSH para CI/CD

Na VPS, como usuario `deploy`:

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_deploy -N ""
cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys

# Copiar a chave privada para adicionar como secret no GitHub
cat ~/.ssh/github_deploy
```

### 11.3. Fluxo de deploy

```
Push para main → CI (lint + tests) → SSH na VPS → git pull → docker compose build → deploy
```

---

## 12. Manutencao e Atualizacoes

### 12.1. Atualizar o sistema operacional

```bash
sudo apt update && sudo apt upgrade -y

# Verificar se precisa reiniciar
[ -f /var/run/reboot-required ] && echo "Reboot necessario"
```

### 12.2. Atualizar Docker images

```bash
cd /opt/marketplace
docker compose pull postgres certbot
docker compose up -d
docker image prune -f  # remover imagens antigas
```

### 12.3. Limpar recursos Docker nao utilizados

```bash
# Remover containers parados, redes orfas e imagens sem tag
docker system prune -f

# Mais agressivo (inclui volumes nao usados - CUIDADO)
# docker system prune -a --volumes
```

### 12.4. Verificar espaco em disco

```bash
df -h
sudo du -sh /var/lib/docker/
docker system df
```

---

## 13. Troubleshooting

### Container nao sobe

```bash
docker compose logs <servico> --tail=50
docker compose ps
docker inspect <container_name>
```

### Banco de dados inacessivel

```bash
# Verificar se esta rodando
docker compose ps postgres

# Conectar diretamente
docker compose exec postgres psql -U qm_app -d quintalmistico

# Verificar logs
docker compose logs postgres --tail=30
```

### Certificado SSL expirado

```bash
docker compose run --rm certbot renew --force-renewal
docker compose exec nginx nginx -s reload
```

### Backend com erro 503

```bash
# Verificar logs
docker compose logs backend --tail=50

# Verificar .env esta correto
cat .env | grep -v "^#" | grep -v "^$"

# Reiniciar
docker compose restart backend
```

### Porta em uso

```bash
sudo lsof -i :80
sudo lsof -i :443
sudo lsof -i :5432
```

### Sem espaco em disco

```bash
# Verificar maiores consumidores
sudo du -sh /var/lib/docker/*
docker system prune -f
sudo journalctl --vacuum-size=100M
```

---

## Checklist Final de Deploy

- [ ] VPS Debian 12 contratada e acessivel via SSH
- [ ] Usuario `deploy` criado com chave SSH
- [ ] SSH configurado (porta customizada, sem root, sem senha)
- [ ] UFW ativado (portas 2222, 80, 443)
- [ ] Fail2Ban ativo
- [ ] Docker e Docker Compose instalados
- [ ] Repositorio clonado em `/opt/marketplace`
- [ ] `.env` configurado com todas as variaveis
- [ ] `docker compose up -d` rodando sem erros
- [ ] Migracoes aplicadas
- [ ] SSL configurado com Let's Encrypt
- [ ] DNS configurado no Cloudflare
- [ ] Backup automatico configurado (crontab)
- [ ] Health monitor configurado (crontab)
- [ ] Secrets do GitHub Actions configurados
- [ ] Deploy automatico funcionando
- [ ] Frontend funcionando no Cloudflare Pages
- [ ] API respondendo em `https://api.quintalmistico.com.br/api/health`
