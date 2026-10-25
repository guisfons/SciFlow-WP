#!/usr/bin/env bash
# HTTP smoke tests for a DISPOSABLE SciFlow install.
#   tests/integration/http-smoke.sh http://127.0.0.1:8099 <admin_user> <admin_pass> <author_user> <author_pass> <article_id>
# Fails (exit 1) on HTTP 5xx, PHP fatal output or leaked content.
set -u
BASE="$1"; ADMIN_U="$2"; ADMIN_P="$3"; AUTH_U="$4"; AUTH_P="$5"; ARTICLE="$6"
JAR_A=$(mktemp); JAR_U=$(mktemp); OUT=$(mktemp)
FAIL=0; PASS=0
ok()   { PASS=$((PASS+1)); echo "  PASS  $1"; }
bad()  { FAIL=$((FAIL+1)); echo "  FAIL  $1"; }

login() { # jar user pass
  curl -s -o /dev/null -c "$1" -b "$1" "$BASE/wp-login.php"
  curl -s -o /dev/null -c "$1" -b "$1" --data-urlencode "log=$2" --data-urlencode "pwd=$3" \
       -d "wp-submit=Entrar&testcookie=1" -b "wordpress_test_cookie=WP%20Cookie%20check" "$BASE/wp-login.php"
}

check_page() { # label url [jar]
  local code
  if [ -n "${3:-}" ]; then code=$(curl -s -o "$OUT" -w '%{http_code}' -b "$3" "$2"); else code=$(curl -s -o "$OUT" -w '%{http_code}' "$2"); fi
  if [ "$code" -ge 500 ] || grep -qiE "critical error|Fatal error|Parse error|Warning: |Notice: " "$OUT"; then
    bad "$1 -> HTTP $code (erro PHP)"
  else
    ok "$1 -> HTTP $code"
  fi
}

login "$JAR_A" "$ADMIN_U" "$ADMIN_P"
login "$JAR_U" "$AUTH_U" "$AUTH_P"

echo "== Páginas públicas e do autor"
for id in $(curl -s "$BASE/?rest_route=/wp/v2/pages&per_page=100&_fields=id" | grep -o '"id":[0-9]*' | cut -d: -f2); do
  check_page "anônimo  page_id=$id" "$BASE/?page_id=$id"
  check_page "autor    page_id=$id" "$BASE/?page_id=$id" "$JAR_U"
  check_page "admin    page_id=$id" "$BASE/?page_id=$id" "$JAR_A"
done

echo "== Telas administrativas"
for p in sciflow-settings sciflow-diagnostics sciflow-ranking sciflow-certificates sciflow-mass-email sciflow-tecnicos sciflow-gestor-permissions sciflow-import-export sciflow-anais; do
  check_page "admin.php?page=$p" "$BASE/wp-admin/admin.php?page=$p" "$JAR_A"
done
check_page "lista de trabalhos (wp-admin)" "$BASE/wp-admin/edit.php?post_type=enfrute_trabalhos" "$JAR_A"
check_page "editar trabalho (wp-admin)" "$BASE/wp-admin/post.php?post=$ARTICLE&action=edit" "$JAR_A"

echo "== Vazamento de dados"
DETAIL=$(curl -s "$BASE/?rest_route=/wp/v2/pages&per_page=100&_fields=id,template" | grep -o '"id":[0-9]*,"template":"page-templates\\/template-article-detail.php"' | head -1 | grep -o '[0-9]*' | head -1)
if [ -n "$DETAIL" ]; then
  code=$(curl -s -o "$OUT" -w '%{http_code}' "$BASE/?page_id=$DETAIL&article_id=$ARTICLE")
  if grep -q "CONTEUDO-SIGILOSO" "$OUT"; then bad "anônimo vê detalhe do trabalho"; else ok "anônimo não vê detalhe do trabalho (HTTP $code)"; fi
  curl -s -o "$OUT" -b "$JAR_U" "$BASE/?page_id=$DETAIL&article_id=$ARTICLE"
  if grep -q "CONTEUDO-SIGILOSO" "$OUT"; then ok "autor vê o próprio trabalho"; else bad "autor não vê o próprio trabalho"; fi
fi
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-content/uploads/certificados/teste@example.test.pdf")
[ "$code" = "403" ] && ok "acesso direto a certificados bloqueado (403)" || bad "acesso direto a certificados -> HTTP $code"
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-content/plugins/sciflow-wp/test-cron.php")
[ "$code" != "200" ] && ok "test-cron.php não executa (HTTP $code)" || bad "test-cron.php respondeu 200"
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{"id":"x","status":"PAID"}' "$BASE/?rest_route=/sciflow/v1/payment-webhook")
ok "webhook responde sem erro de servidor (HTTP $code)"

rm -f "$JAR_A" "$JAR_U" "$OUT"
echo
echo "Result: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
