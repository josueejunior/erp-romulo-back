#!/usr/bin/env python3
"""
Smoke / regressão das rotas de API usadas pelas abas do detalhe de processo (gestor).

Abas cobertas (equivalência API):
  Painel          → GET processo, sugerir-status, exportações leves
  Mural           → GET/POST/DELETE notas (stubs na API)
  Documentos      → GET documentos, download inválido
  Histórico       → não há rota dedicada; valida GET processo (dados exibidos na UI)
  Itens           → GET itens, GET item, PATCH valor-final-disputa inválido (não 5xx)
  Cotações        → GET orcamentos (por processo)
  Sessão/Disputa  → GET/PUT disputa
  Habilitação     → GET/PUT julgamento
  Execução        → saldo, contratos, empenhos, AF, NF (quando processo em fase de execução)

Uso (stdlib apenas — sem pip):
  export ADDSIMP_API_ROOT=http://127.0.0.1:8000
  export ADDSIMP_EMAIL='...'
  export ADDSIMP_PASSWORD='...'
  # opcional: ADDSIMP_PROCESSO_ID=44
  python3 qa/python/processo_detalhe_rotas_smoke.py

Na rede Docker (addsimp-api não inclui Python; use imagem efêmera na mesma rede), exemplo em uma linha:
  docker run --rm --network addsimp-stack_default -v /CAMINHO/erp-romulo-back/qa/python:/qa:ro -e ADDSIMP_API_ROOT=http://addsimp-api:8000 -e ADDSIMP_EMAIL='...' -e ADDSIMP_PASSWORD='...' python:3.12-alpine python /qa/processo_detalhe_rotas_smoke.py
  Substitua /CAMINHO pelo diretório absoluto até a pasta que contém erp-romulo-back.

Códigos esperados: 2xx em rotas válidas autenticadas; 401 sem token;
404/422 em IDs inválidos (nunca 5xx por input malformado).
"""

from __future__ import annotations

import json
import os
import ssl
import sys
import unittest
import urllib.error
import urllib.request
from typing import Any, Dict, Optional, Tuple


def _env(name: str, default: Optional[str] = None) -> Optional[str]:
    v = os.environ.get(name)
    return v if v is not None and v != "" else default


class ApiClient:
    def __init__(self, api_root: str) -> None:
        self.api_root = api_root.rstrip("/")
        self.token: Optional[str] = None
        self.tenant_id: Optional[str] = None
        self.empresa_id: Optional[str] = None
        self._ctx = ssl.create_default_context()

    def request(
        self,
        method: str,
        path: str,
        *,
        json_body: Any = None,
        headers: Optional[Dict[str, str]] = None,
        token: Optional[str] = None,
    ) -> Tuple[int, Any]:
        url = f"{self.api_root}{path}"
        h = {
            "Accept": "application/json",
            "Content-Type": "application/json",
        }
        if headers:
            h.update(headers)
        t = token if token is not None else self.token
        if t:
            h["Authorization"] = f"Bearer {t}"
        if self.tenant_id:
            h["X-Tenant-ID"] = str(self.tenant_id)
        if self.empresa_id:
            h["X-Empresa-ID"] = str(self.empresa_id)

        data = None
        if json_body is not None:
            data = json.dumps(json_body).encode("utf-8")

        req = urllib.request.Request(url, data=data, headers=h, method=method.upper())
        try:
            with urllib.request.urlopen(req, context=self._ctx, timeout=60) as resp:
                raw = resp.read().decode("utf-8", errors="replace")
                code = resp.getcode()
                if not raw:
                    return code, None
                try:
                    return code, json.loads(raw)
                except json.JSONDecodeError:
                    return code, raw
        except urllib.error.HTTPError as e:
            raw = e.read().decode("utf-8", errors="replace")
            try:
                body = json.loads(raw) if raw else None
            except json.JSONDecodeError:
                body = raw
            return e.code, body
        except urllib.error.URLError as e:
            raise RuntimeError(f"Falha de rede ao chamar {url}: {e}") from e

    def login(self, email: str, password: str) -> Dict[str, Any]:
        code, body = self.request(
            "POST",
            "/api/v1/auth/login",
            json_body={"email": email, "password": password},
            token=None,
        )
        if code != 200 or not isinstance(body, dict):
            raise RuntimeError(f"Login falhou HTTP {code}: {body}")
        self.token = body.get("token")
        tenant = body.get("tenant") or {}
        if isinstance(tenant, dict) and tenant.get("id") is not None:
            self.tenant_id = str(tenant["id"])
        user = body.get("user") or {}
        if isinstance(user, dict) and user.get("empresa_ativa_id") is not None:
            self.empresa_id = str(user["empresa_ativa_id"])
        if not self.token:
            raise RuntimeError("Resposta de login sem token")
        return body

    def health(self) -> int:
        # health está fora do prefixo v1
        root = self.api_root.replace("/api/v1", "").rstrip("/")
        if not root:
            root = self.api_root.rstrip("/")
        url = f"{root}/api/health"
        req = urllib.request.Request(url, method="GET")
        try:
            with urllib.request.urlopen(req, context=self._ctx, timeout=15) as resp:
                return resp.getcode()
        except urllib.error.HTTPError as e:
            return e.code


class TestProcessoDetalheRotas(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        api_root = _env("ADDSIMP_API_ROOT", "http://127.0.0.1:8000")
        cls.client = ApiClient(api_root)
        cls.email = _env("ADDSIMP_EMAIL", "josueejunior99@gmail.com")
        cls.password = _env("ADDSIMP_PASSWORD", "91246397")
        cls.processo_id: Optional[int] = None
        cls.item_id: Optional[int] = None

        hc = cls.client.health()
        if hc != 200:
            raise unittest.SkipTest(f"API indisponível (GET /api/health → {hc}). Ajuste ADDSIMP_API_ROOT.")

        cls.client.login(cls.email, cls.password)

        pid = _env("ADDSIMP_PROCESSO_ID")
        if pid and pid.isdigit():
            cls.processo_id = int(pid)
        else:
            code, body = cls.client.request("GET", "/api/v1/processos?per_page=5")
            if code != 200 or not isinstance(body, dict):
                raise unittest.SkipTest(f"Não foi possível listar processos: {code} {body}")
            rows = body.get("data") if isinstance(body.get("data"), list) else body
            if not rows:
                raise unittest.SkipTest("Nenhum processo no tenant para testar.")
            cls.processo_id = int(rows[0]["id"])

        code, proc = cls.client.request("GET", f"/api/v1/processos/{cls.processo_id}")
        if code != 200 or not isinstance(proc, dict):
            raise unittest.SkipTest(f"Processo {cls.processo_id} inacessível: {code}")
        inner = proc.get("data") or proc
        itens = inner.get("itens") if isinstance(inner, dict) else None
        if isinstance(itens, list) and itens:
            cls.item_id = int(itens[0]["id"])

    def _pid(self) -> int:
        assert self.processo_id is not None
        return self.processo_id

    def test_sem_token_lista_processos_401(self) -> None:
        c = ApiClient(self.client.api_root)
        code, _ = c.request("GET", "/api/v1/processos?per_page=1", token="")
        self.assertIn(code, (401, 403), f"Esperado 401/403 sem token, veio {code}")

    def test_processo_id_inexistente_404(self) -> None:
        code, _ = self.client.request("GET", "/api/v1/processos/999999991")
        self.assertEqual(code, 404)

    def test_processo_id_nao_numerico(self) -> None:
        code, _ = self.client.request("GET", "/api/v1/processos/nao-e-numero")
        self.assertIn(code, (404, 400, 422))

    def test_painel_get_processo(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}")
        self.assertEqual(code, 200, body)
        self.assertIsInstance(body, dict)

    def test_painel_sugerir_status(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/sugerir-status")
        self.assertIn(code, (200, 400, 422), f"sugerir-status: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_mural_notas_get_post_delete(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/notas")
        self.assertEqual(code, 200, body)
        code, body = self.client.request(
            "POST",
            f"/api/v1/processos/{self._pid()}/notas",
            json_body={"texto": "smoke QA python"},
        )
        self.assertEqual(code, 200, body)
        code, body = self.client.request("DELETE", f"/api/v1/processos/{self._pid()}/notas/1")
        self.assertEqual(code, 200, body)

    def test_documentos_listar(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/documentos")
        self.assertIn(code, (200, 400, 403), f"documentos: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_documentos_download_id_invalido(self) -> None:
        code, _ = self.client.request(
            "GET",
            f"/api/v1/processos/{self._pid()}/documentos/999999991/download",
        )
        self.assertIn(code, (404, 400, 403, 422))

    def test_historico_via_payload_processo(self) -> None:
        """Histórico na UI agrega dados do processo + notas; garantimos payload principal."""
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}")
        self.assertEqual(code, 200)
        self.assertIsInstance(body, dict)

    def test_itens_lista(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/itens")
        self.assertEqual(code, 200, body)
        self.assertIsInstance(body, dict)

    def test_itens_get_item_invalido(self) -> None:
        code, _ = self.client.request("GET", f"/api/v1/processos/{self._pid()}/itens/999999991")
        self.assertEqual(code, 404)

    def test_itens_get_item_quando_existe(self) -> None:
        if self.item_id is None:
            self.skipTest("Processo sem itens")
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/itens/{self.item_id}")
        self.assertIn(code, (200, 403), f"item {self.item_id}: {body}")

    def test_cotacoes_orcamentos_por_processo(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/orcamentos")
        self.assertIn(code, (200, 400, 403), f"orcamentos: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_disputa_get(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/disputa")
        self.assertIn(code, (200, 400, 403, 422), f"disputa GET: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_disputa_put_vazio_ou_roundtrip(self) -> None:
        code, body = self.client.request(
            "PUT",
            f"/api/v1/processos/{self._pid()}/disputa",
            json_body={"itens": []},
        )
        self.assertIn(code, (200, 400, 403, 422), f"disputa PUT []: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_disputa_put_payload_invalido(self) -> None:
        code, body = self.client.request(
            "PUT",
            f"/api/v1/processos/{self._pid()}/disputa",
            json_body={"itens": [{"id": "x", "valor_final_sessao": "não-número"}]},
        )
        self.assertIn(code, (200, 400, 403, 422), f"disputa PUT inválido: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_julgamento_get(self) -> None:
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/julgamento")
        self.assertIn(code, (200, 400, 403, 422), f"julgamento GET: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_julgamento_put_vazio(self) -> None:
        code, body = self.client.request(
            "PUT",
            f"/api/v1/processos/{self._pid()}/julgamento",
            json_body={"itens": []},
        )
        self.assertIn(code, (200, 400, 403, 422), f"julgamento PUT []: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_julgamento_put_payload_invalido(self) -> None:
        code, body = self.client.request(
            "PUT",
            f"/api/v1/processos/{self._pid()}/julgamento",
            json_body={"itens": [{"id": 1, "status_item": "valor_invalido_xyz"}]},
        )
        self.assertIn(code, (200, 400, 403, 422), f"julgamento PUT inválido: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_item_vinculos_quando_existe(self) -> None:
        if self.item_id is None:
            self.skipTest("Processo sem itens")
        code, body = self.client.request("GET", f"/api/v1/processos/{self._pid()}/itens/{self.item_id}/vinculos")
        self.assertIn(code, (200, 400, 403), f"vinculos: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_item_patch_valor_final_disputa_invalido(self) -> None:
        if self.item_id is None:
            self.skipTest("Processo sem itens")
        code, body = self.client.request(
            "PATCH",
            f"/api/v1/processos/{self._pid()}/itens/{self.item_id}/valor-final-disputa",
            json_body={"valor_final_sessao": "não-é-número"},
        )
        self.assertIn(code, (200, 400, 403, 422), f"valor-final-disputa: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_confirmar_pagamento_sem_dados(self) -> None:
        code, body = self.client.request(
            "POST",
            f"/api/v1/processos/{self._pid()}/confirmar-pagamento",
            json_body={},
        )
        self.assertIn(code, (200, 400, 403, 422), f"confirmar-pagamento: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_download_edital(self) -> None:
        code, _ = self.client.request("GET", f"/api/v1/processos/{self._pid()}/download-edital")
        self.assertIn(code, (200, 302, 400, 403, 404, 422))

    def test_documentos_habilitacao_lista_global(self) -> None:
        code, body = self.client.request("GET", "/api/v1/documentos-habilitacao?per_page=2")
        self.assertIn(code, (200, 400, 403), f"documentos-habilitacao: {body}")
        self.assertNotIn(code, range(500, 600))

    def test_execucao_saldo_e_relacionados(self) -> None:
        """Rotas da aba Execução — podem retornar 400 se processo não estiver na fase."""
        paths = [
            f"/api/v1/processos/{self._pid()}/saldo",
            f"/api/v1/processos/{self._pid()}/saldo-vencido",
            f"/api/v1/processos/{self._pid()}/saldo-vinculado",
            f"/api/v1/processos/{self._pid()}/saldo-empenhado",
            f"/api/v1/processos/{self._pid()}/contratos",
            f"/api/v1/processos/{self._pid()}/empenhos",
            f"/api/v1/processos/{self._pid()}/autorizacoes-fornecimento",
            f"/api/v1/processos/{self._pid()}/notas-fiscais",
            f"/api/v1/processos/{self._pid()}/confirmacoes-pagamento",
        ]
        for p in paths:
            code, body = self.client.request("GET", p)
            self.assertIn(
                code,
                (200, 400, 403, 404, 422),
                f"{p} → {code}: {body}",
            )
            self.assertNotIn(code, range(500, 600), p)

    def test_exportacoes_proposta_comercial(self) -> None:
        code, _ = self.client.request(
            "GET",
            f"/api/v1/processos/{self._pid()}/exportar/proposta-comercial",
        )
        # Pode falhar por permissão ou dados incompletos; não deve estourar 5xx por rota inexistente
        self.assertIn(code, (200, 400, 403, 422))
        self.assertNotIn(code, range(500, 600))


def main() -> int:
    loader = unittest.defaultTestLoader.loadTestsFromTestCase(TestProcessoDetalheRotas)
    suite = unittest.TestSuite(loader)
    runner = unittest.TextTestRunner(verbosity=2, failfast=False)
    result = runner.run(suite)
    return 0 if result.wasSuccessful() else 1


if __name__ == "__main__":
    sys.exit(main())
