import crypto from "node:crypto";
import { createClient } from "@supabase/supabase-js";

function pickHeader(headers, name) {
  const key = Object.keys(headers || {}).find(k => k.toLowerCase() === name.toLowerCase());
  return key ? headers[key] : "";
}

function padOrder(id) {
  return String(id).padStart(6, "0");
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
    body: JSON.stringify({
      from,
      to,
      subject,
      html
    })
  });

  if (!resp.ok) {
    const txt = await resp.text();
    throw new Error("Resend falhou: " + txt);
  }
}

export async function handler(event) {
  if (event.httpMethod !== "POST") {
    return { statusCode: 405, body: "Method Not Allowed" };
  }

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

  const nome = (data.nome || "").trim();
  const telemovel = (data.telemovel || "").trim();
  const email = (data.email || "").trim();
  const servico = (data.servico || "").trim();
  const dataPretendida = (data.data || "").trim();
  const horaPretendida = (data.hora || "").trim();
  const obs = (data.obs || "").trim();

  const errors = [];
  if (!nome) errors.push("Nome obrigatório");
  if (!telemovel) errors.push("Telemóvel obrigatório");
  if (!email) errors.push("Email obrigatório");
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push("Email inválido");
  if (!servico) errors.push("Serviço obrigatório");
  if (!isValidDate(dataPretendida)) errors.push("Data inválida");
  if (!isValidTime(horaPretendida)) errors.push("Hora inválida");

  if (errors.length) {
    return { statusCode: 400, body: errors.join(" | ") };
  }

  const supabaseUrl = process.env.SUPABASE_URL;
  const supabaseKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
  if (!supabaseUrl || !supabaseKey) {
    return { statusCode: 500, body: "Supabase env vars em falta (SUPABASE_URL / SUPABASE_SERVICE_ROLE_KEY)." };
  }

  const siteUrl = (process.env.SITE_URL || "").replace(/\/+$/, "");
  if (!siteUrl) {
    return { statusCode: 500, body: "SITE_URL não configurado no Netlify (ex.: https://teusite.netlify.app)" };
  }

  const notifyTo = process.env.NOTIFY_TO_EMAIL;
  if (!notifyTo) {
    return { statusCode: 500, body: "NOTIFY_TO_EMAIL não configurado no Netlify (email da tua mãe)." };
  }

  const supabase = createClient(supabaseUrl, supabaseKey);

  try {
    const token = crypto.randomBytes(32).toString("hex");
    const expiresAt = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString();

    const horaDb = horaPretendida.length === 5 ? `${horaPretendida}:00` : horaPretendida;

    const { data: row, error } = await supabase
      .from("booking_requests")
      .insert([{
        nome,
        telemovel,
        email,
        servico,
        data_pretendida: dataPretendida,
        hora_pretendida: horaDb,
        obs: obs || null,
        action_token: token,
        token_expires_at: expiresAt
      }])
      .select("*")
      .single();

    if (error) throw error;

    const id = row.id;
    const pedidoNum = padOrder(id);

    const acceptUrl = `${siteUrl}/marcacao-acao?action=accept&id=${id}&token=${token}`;
    const declineUrl = `${siteUrl}/marcacao-acao?action=decline&id=${id}&token=${token}`;
    const reschedUrl = `${siteUrl}/marcacao-acao?action=reschedule&id=${id}&token=${token}`;

    const safeObs = obs ? obs.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>") : '<span style="color:#777;">(sem observações)</span>';

    const subject = `Novo pedido de marcação #${pedidoNum}`;

    const html = `
      <div style="font-family:Arial,sans-serif;background:#f6f6f6;padding:22px;">
        <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
          <div style="padding:18px 20px;background:#111;color:#fff;">
            <div style="font-size:14px;opacity:.9;">Sónia Cabeleireiro</div>
            <div style="font-size:20px;font-weight:700;margin-top:6px;">Pedido de marcação #${pedidoNum}</div>
          </div>

          <div style="padding:18px 20px;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
              <tr><td style="padding:8px 0;color:#666;width:170px;">Nome</td><td style="padding:8px 0;font-weight:700;">${nome}</td></tr>
              <tr><td style="padding:8px 0;color:#666;">Telemóvel</td><td style="padding:8px 0;font-weight:700;">${telemovel}</td></tr>
              <tr><td style="padding:8px 0;color:#666;">Email</td><td style="padding:8px 0;">${email}</td></tr>
              <tr><td style="padding:8px 0;color:#666;">Serviço</td><td style="padding:8px 0;">${servico}</td></tr>
              <tr><td style="padding:8px 0;color:#666;">Data/Hora</td><td style="padding:8px 0;">${dataPretendida} às ${horaPretendida}</td></tr>
              <tr><td style="padding:8px 0;color:#666;vertical-align:top;">Observações</td><td style="padding:8px 0;line-height:1.5;">${safeObs}</td></tr>
            </table>

            <div style="margin-top:18px;padding-top:16px;border-top:1px solid #eee;">
              <div style="font-size:13px;color:#666;margin-bottom:10px;">
                Escolhe uma opção (o link expira em 7 dias):
              </div>

              <a href="${acceptUrl}" style="display:inline-block;margin:6px 10px 6px 0;background:#16a34a;color:#fff;text-decoration:none;padding:12px 14px;border-radius:8px;font-weight:700;">Aceitar</a>
              <a href="${declineUrl}" style="display:inline-block;margin:6px 10px 6px 0;background:#dc2626;color:#fff;text-decoration:none;padding:12px 14px;border-radius:8px;font-weight:700;">Recusar</a>
              <a href="${reschedUrl}" style="display:inline-block;margin:6px 10px 6px 0;background:#111827;color:#fff;text-decoration:none;padding:12px 14px;border-radius:8px;font-weight:700;">Remarcar</a>
            </div>

            <div style="margin-top:14px;font-size:12px;color:#777;">
              Se os botões não funcionarem, copia e cola no browser:<br>
              <span style="color:#111;">${acceptUrl}</span>
            </div>
          </div>
        </div>
      </div>
    `;

    await sendResendEmail({
      to: notifyTo,
      subject,
      html
    });

    return {
      statusCode: 303,
      headers: { Location: `/obrigado.html?id=${encodeURIComponent(id)}` },
      body: ""
    };

  } catch (err) {
    return { statusCode: 500, body: `Erro: ${err.message || String(err)}` };
  }
}