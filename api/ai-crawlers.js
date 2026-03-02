import { Redis } from "@upstash/redis";

export default async function handler(req, res) {
  // Crawler data is public — no auth required
  const kvUrl = process.env.KV_REST_API_URL;
  const kvToken = process.env.KV_REST_API_TOKEN;
  if (!kvUrl || !kvToken) {
    return res.status(500).json({ error: "Redis not configured. Add Vercel KV from Storage tab." });
  }

  const redis = new Redis({
    url: kvUrl,
    token: kvToken,
  });

  try {
    // Get all data in parallel
    const [visits, totals, bots] = await Promise.all([
      redis.lrange("ai:visits", 0, 99),
      redis.hgetall("ai:totals"),
      redis.smembers("ai:bots"),
    ]);

    // Get daily stats for the last 7 days
    const daily = {};
    const today = new Date();
    for (let i = 0; i < 7; i++) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      const dateStr = d.toISOString().slice(0, 10);
      daily[dateStr] = {};
      for (const bot of bots || []) {
        const count = await redis.get(`ai:daily:${dateStr}:${bot}`);
        if (count) daily[dateStr][bot] = Number(count);
      }
    }

    // Get page stats per bot
    const pages = {};
    for (const bot of bots || []) {
      const pageData = await redis.hgetall(`ai:pages:${bot}`);
      if (pageData && Object.keys(pageData).length > 0) {
        pages[bot] = pageData;
      }
    }

    // Parse visit entries
    const recentVisits = (visits || []).map((v) => {
      if (typeof v === "string") {
        try { return JSON.parse(v); } catch { return v; }
      }
      return v;
    });

    res.setHeader("Cache-Control", "no-store");
    return res.status(200).json({
      summary: {
        totalBots: (bots || []).length,
        totalVisits: Object.values(totals || {}).reduce((a, b) => a + Number(b), 0),
        bots: bots || [],
      },
      totals: totals || {},
      daily,
      pages,
      recentVisits,
    });
  } catch (e) {
    return res.status(500).json({ error: e.message });
  }
}
