# Деплой читает настройки из .vscode/sftp.json — того же файла,
# который использует расширение VS Code SFTP. Загрузка идёт через
# misc/bga_sftp.py (Paramiko): OpenSSH sftp/scp на этом сервере зависают.

BGA_PY         := $(HOME)/.bga-tools/venv/bin/python
BGA_SFTP       := misc/bga_sftp.py
BGA_REMOTE_DIR := /bigtop

DEPLOY_ROOT   := dbmodel.sql $(wildcard gameinfos.jsonc gameinfos.inc.php stats.json stats.jsonc gameoptions.json gameoptions.jsonc gamepreferences.json gamepreferences.jsonc)
DEPLOY_PHP    := $(wildcard modules/php/*.php)
DEPLOY_STATES := $(wildcard modules/php/States/*.php)
DEPLOY_JS     := $(wildcard modules/js/*.js)

check:
	@for f in $(DEPLOY_PHP) $(DEPLOY_STATES); do php -l "$$f" || exit 1; done
	@node --check modules/js/Game.js
	@echo "OK: PHP и JS"

deploy: check
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_ROOT) $(BGA_REMOTE_DIR)/
	$(BGA_PY) $(BGA_SFTP) put bigtop.css $(BGA_REMOTE_DIR)/
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_PHP) $(BGA_REMOTE_DIR)/modules/php/
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_STATES) $(BGA_REMOTE_DIR)/modules/php/States/
	$(BGA_PY) $(BGA_SFTP) put $(DEPLOY_JS) $(BGA_REMOTE_DIR)/modules/js/
	$(BGA_PY) $(BGA_SFTP) put img/* $(BGA_REMOTE_DIR)/img/
	@echo "OK: файлы загружены"
