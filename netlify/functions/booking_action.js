import { createClient } from "@supabase/supabase-js";

function pickHeader(headers, name) {
  const key = Object.keys(headers || {}).find(k => k.toLowerCase() === name.toLowerCase());
  return key ? headers[key] : "";
}

function padOrder(id) {
  return String(id).padStart(6, "0");
}

function esc(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function isValidDate(ymd) {
  return /^\d{4}-\d{2}-\d{2}$/.test(ymd);
}

function isValidTime(hm) {
  return /^\d{2}:\d{2}$/.test(hm);
}

async function sendResendEmail({ to, subject, html }) {
  const apiKey = process.env.RESEND_API_KEY;
  if (!apiKey) throw new Error("RESEND_API_KEY não configurada no Netlify.");

  const from = process.env.MAIL_FROM || "Sónia Cabeleireiro <onboarding@resend.dev>";

  const resp = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${apiKey}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify({ from, to, subject, html })
  });

  if (!resp.ok) {
    const txt = await resp.text();
    throw new Error("Resend falhou: " + txt);
  }
}

function pageHtml(title, body) {
  return `<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${esc(title)}</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <main class="container" style="padding:24px 0;">
    ${body}
  </main>
</body>
</html>`;
}

export async function handler(event) {
  const supabaseUrl = process.env.SUPABASE_URL;
  const supabaseKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
  if (!supabaseUrl || !supabaseKey) {
    return { statusCode: 500, body: "Supabase env vars em falta." };
  }
  const supabase = createClient(supabaseUrl, supabaseKey);

  // GET -> mostra página de confirmação
  // POST -> executa ação e notifica cliente
  const method = event.httpMethod;

  const qs = event.queryStringParameters || {};
  let action = (qs.action || "").trim();
  let id = parseInt(qs.id || "0", 10);
  let token = (qs.token || "").trim();

  // Em POST vamos ler do body (form)
  if (method === "POST") {
    const contentType = pickHeader(event.headers, "content-type");
    let data = {};
    try {
      if (contentType.includes("application/json")) {
        data = JSON.parse(event.body || "{}");
      } else {
        data = Object.fromEntries(new URLSearchParams(event.body || ""));
      }
    } catch (e) {
      return { statusCode: 400, body: "Body inválido." };
    }

    action = (data.action || action || "").trim();
    id = parseInt(data.id || id || "0", 10);
    token = (data.token || token || "").trim();

    const dataNova = (data.data_nova || "").trim();
    const horaNova = (data.hora_nova || "").trim();

    const allowed = ["accept", "decline", "reschedule"];
    if (!allowed.includes(action) || !id || !token) {
      return {
        statusCode: 400,
        headers: { "Content-Type": "text/html; charset=utf-8" },
        body: pageHtml("Link inválido", `<div class="card"><h1>Link inválido</h1><p>Este link não é válido.</p></div>`)
      };
    }

    // buscar pedido
    const { data: req, error: err1 } = await supabase
      .from("booking_requests")
      .select("*")
      .eq("id", id)
      .eq("action_token", token)
      .maybeSingle();

    if (err1) {
      return { statusCode: 500, body: "Erro ao consultar pedido." };
    }
    if (!req) {
      return {
        statusCode: 404,
        headers: { "Content-Type": "text/html; charset=utf-8" },
        body: pageHtml("Pedido não encontrado", `<div class="card"><h1>Pedido não encontrado</h1><p>Token inválido ou pedido inexistente.</p></div>`)
      };
    }

    // validade token
    const exp = new Date(req.token_expires_at);
    if (new Date() > exp) {
      return {
        statusCode: 410,
        headers: { "Content-Type": "text/html; charset=utf-8" },
        body: pageHtml("Link expirado", `<div class="card"><h1>Link expirado</h1><p>Este link já expirou.</p></div>`)
      };
    }

    if (req.estado !== "pendente") {
      return {
        statusCode: 200,
        headers: { "Content-Type": "text/html; charset=utf-8" },
        body: pageHtml("Pedido já tratado", `<div class="card"><h1>Pedido #${esc(padOrder(req.id))}</h1><p>Este pedido já foi tratado (estado: <strong>${esc(req.estado)}</strong>).</p></div>`)
      };
    }

    // atualizar estado
    let newEstado = req.estado;
    let finalDate = req.data_pretendida;
    let finalTime = req.hora_pretendida;

    if (action === "accept") {
      newEstado = "aceite";
      finalDate = req.data_pretendida;
      finalTime = req.hora_pretendida;
    }

    if (action === "decline") {
      newEstado = "recusado";
      finalDate = req.data_pretendida;
      finalTime = req.hora_pretendida;
    }

    if (action === "reschedule") {
      if (!isValidDate(dataNova) || !isValidTime(horaNova)) {
        return {
          statusCode: 400,
          headers: { "Content-Type": "text/html; charset=utf-8" },
          body: pageHtml("Dados inválidos", `<div class="card"><h1>Dados inválidos</h1><p>Indica uma data e hora válidas para remarcar.</p></div>`)
        };
      }
      newEstado = "remarcado";
      finalDate = dataNova;
      finalTime = horaNova.length === 5 ? `${horaNova}:00` : horaNova;
    }

    const nowIso = new Date().toISOString();

    const updatePayload = {
      estado: newEstado,
      token_expires_at: nowIso
    };

    if (newEstado === "aceite") {
      updatePayload.data_final = req.data_pretendida;
      updatePayload.hora_final = req.hora_pretendida;
    } else if (newEstado === "remarcado") {
      updatePayload.data_final = finalDate;
      updatePayload.hora_final = finalTime;
    }

    const { error: errUp } = await supabase
      .from("booking_requests")
      .update(updatePayload)
      .eq("id", id)
      .eq("action_token", token);

    if (errUp) {
      return {
        statusCode: 500,
        headers: { "Content-Type": "text/html; charset=utf-8" },
        body: pageHtml("Erro", `<div class="card"><h1>Erro</h1><p>Não foi possível atualizar o pedido.</p></div>`)
      };
    }

    // Email ao cliente (se existir)
    let notice = "";
    const clientEmail = (req.email || "").trim();
    const pedidoNum = padOrder(req.id);

    if (clientEmail) {
      const dataFmt = finalDate; // (podes formatar para dd/mm/yyyy se quiseres)
      const horaFmt = String(finalTime).slice(0, 5);

      let subject = "";
      let headline = "";
      let msg = "";
      let badgeBg = "#111827";

      if (newEstado === "aceite") {
        subject = `Marcação confirmada #${pedidoNum} — ${dataFmt} às ${horaFmt}`;
        headline = "Marcação confirmada";
        msg = "A tua marcação foi <strong>confirmada</strong>.";
        badgeBg = "#16a34a";
      } else if (newEstado === "recusado") {
        subject = `Marcação recusada #${pedidoNum}`;
        headline = "Marcação recusada";
        msg = "Infelizmente, não foi possível confirmar a tua marcação para esta data/hora.";
        badgeBg = "#dc2626";
      } else {
        subject = `Marcação remarcada #${pedidoNum} — ${dataFmt} às ${horaFmt}`;
        headline = "Marcação remarcada";
        msg = "A tua marcação foi <strong>remarcada</strong>. Verifica os novos detalhes abaixo.";
        badgeBg = "#111827";
      }

      const html = `
        <div style="font-family:Arial,sans-serif;background:#f6f6f6;padding:22px;">
          <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
            <div style="padding:18px 20px;background:#111;color:#fff;">
              <div style="font-size:14px;opacity:.9;">Sónia Cabeleireiro</div>
              <div style="font-size:20px;font-weight:700;margin-top:6px;">${headline} #${pedidoNum}</div>
            </div>

            <div style="padding:18px 20px;color:#111;">
              <div style="display:inline-block;background:${badgeBg};color:#fff;padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px;">
                ${newEstado.toUpperCase()}
              </div>

              <p style="margin:12px 0 0 0;line-height:1.6;">
                Olá <strong>${esc(req.nome)}</strong>,<br>
                ${msg}
              </p>

              <table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:12px;">
                <tr><td style="padding:8px 0;color:#666;width:170px;">Serviço</td><td style="padding:8px 0;font-weight:700;">${esc(req.servico)}</td></tr>
                <tr><td style="padding:8px 0;color:#666;">Data/Hora</td><td style="padding:8px 0;font-weight:700;">${esc(dataFmt)} às ${esc(horaFmt)}</td></tr>
              </table>

              <div style="margin-top:14px;padding-top:14px;border-top:1px solid #eee;font-size:13px;color:#555;line-height:1.6;">
                Se precisares de alterar ou cancelar, por favor contacta-nos:<br>
                <strong>+351 933 148 593</strong>
              </div>
            </div>
          </div>
        </div>
      `;

      try {
        await sendResendEmail({ to: clientEmail, subject, html });
        notice = `Cliente notificado por email: ${esc(clientEmail)}`;
      } catch (mailErr) {
        notice = `A ação foi guardada, mas falhou o email ao cliente (${esc(clientEmail)}).`;
      }
    } else {
      notice = "A ação foi guardada, mas o cliente não indicou email.";
    }

    const body = `
      <div class="card">
        <h1>Pedido #${esc(pedidoNum)}</h1>
        <p style="color:var(--muted);margin-top:6px;">Estado atual: <strong>${esc(newEstado)}</strong></p>
        <p style="margin-top:12px;">${notice}</p>
        <p style="color:var(--muted);line-height:1.6;">
          Nome: <strong>${esc(req.nome)}</strong><br>
          Telemóvel: <strong>${esc(req.telemovel)}</strong><br>
          Serviço: <strong>${esc(req.servico)}</strong><br>
          Data/Hora final: <strong>${esc(finalDate)} às ${esc(String(finalTime).slice(0,5))}</strong>
        </p>
      </div>
    `;

    return {
      statusCode: 200,
      headers: { "Content-Type": "text/html; charset=utf-8" },
      body: pageHtml("Pedido atualizado", body)
    };
  }

  // GET
  const allowed = ["accept", "decline", "reschedule"];
  if (!allowed.includes(action) || !id || !token) {
    return {
      statusCode: 400,
      headers: { "Content-Type": "text/html; charset=utf-8" },
      body: pageHtml("Link inválido", `<div class="card"><h1>Link inválido</h1><p>Este link não é válido.</p></div>`)
    };
  }

  const { data: req, error } = await supabase
    .from("booking_requests")
    .select("*")
    .eq("id", id)
    .eq("action_token", token)
    .maybeSingle();

  if (error) {
    return { statusCode: 500, body: "Erro ao consultar pedido." };
  }
  if (!req) {
    return {
      statusCode: 404,
      headers: { "Content-Type": "text/html; charset=utf-8" },
      body: pageHtml("Pedido não encontrado", `<div class="card"><h1>Pedido não encontrado</h1><p>Token inválido ou pedido inexistente.</p></div>`)
    };
  }

  const exp = new Date(req.token_expires_at);
  if (new Date() > exp) {
    return {
      statusCode: 410,
      headers: { "Content-Type": "text/html; charset=utf-8" },
      body: pageHtml("Link expirado", `<div class="card"><h1>Link expirado</h1><p>Este link já expirou.</p></div>`)
    };
  }

  const pedidoNum = padOrder(req.id);

  if (req.estado !== "pendente") {
    return {
      statusCode: 200,
      headers: { "Content-Type": "text/html; charset=utf-8" },
      body: pageHtml("Pedido já tratado", `<div class="card"><h1>Pedido #${esc(pedidoNum)}</h1><p>Este pedido já foi tratado (estado: <strong>${esc(req.estado)}</strong>).</p></div>`)
    };
  }

  let actionTitle = "";
  if (action === "accept") actionTitle = "Confirmar ACEITAR";
  if (action === "decline") actionTitle = "Confirmar RECUSAR";
  if (action === "reschedule") actionTitle = "Confirmar REMARCAR";

  const commonHidden = `
    <input type="hidden" name="action" value="${esc(action)}">
    <input type="hidden" name="id" value="${esc(id)}">
    <input type="hidden" name="token" value="${esc(token)}">
  `;

  const details = `
    <div style="line-height:1.8;color:#111;">
      <div><strong>Nome:</strong> ${esc(req.nome)}</div>
      <div><strong>Telemóvel:</strong> ${esc(req.telemovel)}</div>
      <div><strong>Email:</strong> ${req.email ? esc(req.email) : '<span style="color:#666;">(não indicado)</span>'}</div>
      <div><strong>Serviço:</strong> ${esc(req.servico)}</div>
      <div><strong>Data/Hora pretendida:</strong> ${esc(req.data_pretendida)} às ${esc(String(req.hora_pretendida).slice(0,5))}</div>
    </div>
  `;

  let formHtml = "";
  if (action === "reschedule") {
    formHtml = `
      <form method="post" action="/marcacao-acao">
        ${commonHidden}
        <div class="form-grid" style="margin-top:10px;">
          <div>
            <label>Nova data</label>
            <input type="date" name="data_nova" required>
          </div>
          <div>
            <label>Nova hora</label>
            <input type="time" name="hora_nova" required>
          </div>
          <div class="full">
            <button class="btn btn-primary" type="submit">${esc(actionTitle)}</button>
          </div>
        </div>
      </form>
    `;
  } else if (action === "decline") {
    formHtml = `
      <form method="post" action="/marcacao-acao" style="margin-top:12px;">
        ${commonHidden}
        <button class="btn btn-dark" type="submit">${esc(actionTitle)}</button>
      </form>
    `;
  } else {
    formHtml = `
      <form method="post" action="/marcacao-acao" style="margin-top:12px;">
        ${commonHidden}
        <button class="btn btn-primary" type="submit">${esc(actionTitle)}</button>
      </form>
    `;
  }

  const body = `
    <div class="card">
      <h1>Pedido #${esc(pedidoNum)}</h1>
      <p style="color:var(--muted);margin-top:6px;">Ação: <strong>${esc(action)}</strong></p>
      <div style="margin-top:12px;">${details}</div>
      <hr style="border:0;border-top:1px solid var(--border);margin:16px 0;">
      ${formHtml}
    </div>
  `;

  return {
    statusCode: 200,
    headers: { "Content-Type": "text/html; charset=utf-8" },
    body: pageHtml("Gerir marcação", body)
  };
}