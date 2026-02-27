import { Redis } from "@upstash/redis";

const AI_CRAWLERS = {
  GPTBot: "OpenAI",
  "ChatGPT-User": "OpenAI",
  "Google-Extended": "Google",
  Googlebot: "Google",
  "Anthropic-AI": "Anthropic",
  ClaudeBot: "Anthropic",
  "Claude-Web": "Anthropic",
  PerplexityBot: "Perplexity",
  Bytespider: "ByteDance",
  CCBot: "Common Crawl",
  "Cohere-ai": "Cohere",
  "Meta-ExternalAgent": "Meta",
  "Meta-ExternalFetcher": "Meta",
  Applebot: "Apple",
  "Applebot-Extended": "Apple",
  Bingbot: "Microsoft",
  "FacebookExternalHit": "Meta",
  "Amazonbot": "Amazon",
  "YouBot": "You.com",
  "AI2Bot": "AI2",
  "Ai2Bot-Dolma": "AI2",
  "OAI-SearchBot": "OpenAI",
  "Timpibot": "Timpi",
  "Diffbot": "Diffbot",
  "Scrapy": "Scrapy",
  "PetalBot": "Petal",
  "Webzio-Extended": "Webz.io",
};

function detectAICrawler(userAgent) {
  if (!userAgent) return null;
  for (const [pattern, org] of Object.entries(AI_CRAWLERS)) {
    if (userAgent.includes(pattern)) {
      return { bot: pattern, org };
    }
  }
  return null;
}

export default async function middleware(request) {
  const ua = request.headers.get("user-agent") || "";
  const crawler = detectAICrawler(ua);

  if (!crawler) return new Response(null, { status: 200, headers: { "x-middleware-next": "1" } });

  // Only log if Redis is configured (Vercel KV uses these env var names)
  const kvUrl = process.env.KV_REST_API_URL;
  const kvToken = process.env.KV_REST_API_TOKEN;
  if (!kvUrl || !kvToken) {
    return new Response(null, { status: 200, headers: { "x-middleware-next": "1" } });
  }

  try {
    const redis = new Redis({
      url: kvUrl,
      token: kvToken,
    });

    const url = new URL(request.url);
    const now = new Date().toISOString();
    const today = now.slice(0, 10);

    const logEntry = JSON.stringify({
      bot: crawler.bot,
      org: crawler.org,
      path: url.pathname,
      ts: now,
      ua: ua.slice(0, 200),
    });

    // Store recent visits (keep last 500)
    await redis.lpush("ai:visits", logEntry);
    await redis.ltrim("ai:visits", 0, 499);

    // Daily counter per bot
    const dailyKey = `ai:daily:${today}:${crawler.bot}`;
    await redis.incr(dailyKey);
    await redis.expire(dailyKey, 90 * 86400); // keep 90 days

    // Total counter per bot
    await redis.hincrby("ai:totals", crawler.bot, 1);

    // Track unique bots seen
    await redis.sadd("ai:bots", crawler.bot);

    // Page hit counter per bot
    await redis.hincrby(`ai:pages:${crawler.bot}`, url.pathname, 1);
  } catch (e) {
    // Silently fail — don't block the response
  }

  return new Response(null, { status: 200, headers: { "x-middleware-next": "1" } });
}

export const config = {
  matcher: ["/((?!admin|api|_next|favicon.ico).*)"],
};
