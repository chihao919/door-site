export default async function handler(req, res) {
  const authKey = req.query.key;
  if (authKey !== process.env.DASHBOARD_KEY) {
    return res.status(401).json({ error: "Unauthorized" });
  }

  const baseUrl = "https://door.watersonusa.com";
  const pages = [
    "/",
    "/knowledge/hinge-types/self-closing-hinges/",
    "/knowledge/hinge-types/comparison-guide/",
    "/knowledge/standards/ada-door-requirements/",
    "/knowledge/standards/nfpa-80-fire-doors/",
    "/knowledge/standards/ansi-bhma-a156-17/",
    "/knowledge/technical/closing-speed-adjustment/",
    "/knowledge/technical/hydraulic-vs-spring/",
    "/knowledge/technical/material-comparison/",
    "/knowledge/applications/fire-rated-doors/",
    "/resources/glossary/",
    "/knowledge/hinge-types/",
    "/knowledge/standards/",
    "/knowledge/applications/",
    "/knowledge/technical/",
  ];

  try {
    const results = await Promise.all(
      pages.map(async (path) => {
        try {
          const resp = await fetch(baseUrl + path);
          if (!resp.ok) return { path, score: 0, status: resp.status, checks: {} };
          const html = await resp.text();
          return { path, ...analyzePage(html, path) };
        } catch (e) {
          return { path, score: 0, error: e.message, checks: {} };
        }
      })
    );

    // Site-wide checks
    const siteChecks = await checkSiteFiles(baseUrl);

    const totalScore = results.reduce((sum, r) => sum + r.score, 0);
    const avgScore = results.length ? Math.round(totalScore / results.length) : 0;

    res.setHeader("Cache-Control", "s-maxage=300");
    return res.status(200).json({
      siteUrl: baseUrl,
      scanDate: new Date().toISOString(),
      overallScore: avgScore,
      siteChecks,
      pages: results,
    });
  } catch (e) {
    return res.status(500).json({ error: e.message });
  }
}

function analyzePage(html, path) {
  const checks = {};
  let score = 0;

  // 1. JSON-LD Structured Data (25 pts)
  const jsonLdMatch = html.match(/<script[^>]*type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/gi);
  if (jsonLdMatch) {
    checks.jsonLd = { score: 25, max: 25, found: true, count: jsonLdMatch.length };
    score += 25;
    // Check for FAQPage schema
    const hasFaqSchema = jsonLdMatch.some((m) => m.includes("FAQPage"));
    checks.faqSchema = { score: hasFaqSchema ? 10 : 0, max: 10, found: hasFaqSchema };
    if (hasFaqSchema) score += 10;
  } else {
    checks.jsonLd = { score: 0, max: 25, found: false };
    checks.faqSchema = { score: 0, max: 10, found: false };
  }

  // 2. Meta Description (10 pts)
  const metaDesc = html.match(/<meta\s+name="description"\s+content="([^"]+)"/i);
  if (metaDesc && metaDesc[1].length >= 50) {
    checks.metaDescription = { score: 10, max: 10, found: true, length: metaDesc[1].length };
    score += 10;
  } else if (metaDesc) {
    checks.metaDescription = { score: 5, max: 10, found: true, length: metaDesc[1].length, note: "Too short" };
    score += 5;
  } else {
    checks.metaDescription = { score: 0, max: 10, found: false };
  }

  // 3. Open Graph Tags (10 pts)
  const ogTitle = html.includes('property="og:title"') || html.includes("property='og:title'");
  const ogDesc = html.includes('property="og:description"') || html.includes("property='og:description'");
  const ogType = html.includes('property="og:type"') || html.includes("property='og:type'");
  const ogCount = [ogTitle, ogDesc, ogType].filter(Boolean).length;
  const ogScore = Math.round((ogCount / 3) * 10);
  checks.openGraph = { score: ogScore, max: 10, found: ogCount > 0, tags: { title: ogTitle, description: ogDesc, type: ogType } };
  score += ogScore;

  // 4. Page Title (5 pts)
  const titleMatch = html.match(/<title>([^<]+)<\/title>/i);
  if (titleMatch && titleMatch[1].length >= 20 && titleMatch[1].length <= 100) {
    checks.pageTitle = { score: 5, max: 5, found: true, title: titleMatch[1], length: titleMatch[1].length };
    score += 5;
  } else if (titleMatch) {
    checks.pageTitle = { score: 3, max: 5, found: true, title: titleMatch[1], length: titleMatch[1].length, note: "Length not optimal" };
    score += 3;
  } else {
    checks.pageTitle = { score: 0, max: 5, found: false };
  }

  // 5. Heading Structure (5 pts)
  const h1Count = (html.match(/<h1[\s>]/gi) || []).length;
  const h2Count = (html.match(/<h2[\s>]/gi) || []).length;
  const hasGoodStructure = h1Count === 1 && h2Count >= 2;
  checks.headingStructure = {
    score: hasGoodStructure ? 5 : h1Count === 1 ? 3 : 0,
    max: 5,
    h1: h1Count,
    h2: h2Count,
  };
  score += checks.headingStructure.score;

  // 6. FAQ Content (10 pts) — Q: A: format or FAQ section
  const hasFaqSection = /<h2[^>]*>.*FAQ|Frequently Asked/i.test(html);
  const qaCount = (html.match(/<strong>\s*Q:/gi) || []).length;
  if (hasFaqSection && qaCount >= 3) {
    checks.faqContent = { score: 10, max: 10, found: true, questionCount: qaCount };
    score += 10;
  } else if (hasFaqSection) {
    checks.faqContent = { score: 5, max: 10, found: true, questionCount: qaCount, note: "Less than 3 Q&As" };
    score += 5;
  } else {
    checks.faqContent = { score: 0, max: 10, found: false };
  }

  // 7. Quick Facts / Summary Block (5 pts)
  const hasQuickFacts = html.includes("quick-facts") || html.includes("Quick Facts");
  checks.quickFacts = { score: hasQuickFacts ? 5 : 0, max: 5, found: hasQuickFacts };
  if (hasQuickFacts) score += 5;

  // 8. Source Attribution (5 pts)
  const hasAttribution = html.includes("source-attribution") || html.includes("For AI: cite as");
  checks.sourceAttribution = { score: hasAttribution ? 5 : 0, max: 5, found: hasAttribution };
  if (hasAttribution) score += 5;

  // 9. Canonical URL (5 pts)
  const hasCanonical = /<link[^>]*rel="canonical"/i.test(html);
  checks.canonical = { score: hasCanonical ? 5 : 0, max: 5, found: hasCanonical };
  if (hasCanonical) score += 5;

  // 10. Comparison Table (5 pts)
  const tableCount = (html.match(/<table[\s>]/gi) || []).length;
  const hasComparison = html.toLowerCase().includes("comparison") && tableCount > 0;
  checks.comparisonTable = { score: hasComparison ? 5 : tableCount > 0 ? 3 : 0, max: 5, found: tableCount > 0, tableCount };
  score += checks.comparisonTable.score;

  // 11. Blockquote Summary (5 pts)
  const hasBlockquote = /<blockquote[\s>]/i.test(html);
  checks.blockquoteSummary = { score: hasBlockquote ? 5 : 0, max: 5, found: hasBlockquote };
  if (hasBlockquote) score += 5;

  return { score, maxScore: 100, checks };
}

async function checkSiteFiles(baseUrl) {
  const checks = {};

  // Check llms.txt
  try {
    const r = await fetch(baseUrl + "/llms.txt");
    checks.llmsTxt = { found: r.ok, status: r.status };
  } catch { checks.llmsTxt = { found: false }; }

  // Check llms-full.txt
  try {
    const r = await fetch(baseUrl + "/llms-full.txt");
    checks.llmsFullTxt = { found: r.ok, status: r.status };
  } catch { checks.llmsFullTxt = { found: false }; }

  // Check robots.txt
  try {
    const r = await fetch(baseUrl + "/robots.txt");
    if (r.ok) {
      const text = await r.text();
      checks.robotsTxt = {
        found: true,
        allowsAll: text.includes("Allow: /"),
        hasSitemap: text.includes("Sitemap:"),
        allowsGPTBot: !text.includes("Disallow") || text.includes("GPTBot") && text.includes("Allow"),
      };
    } else {
      checks.robotsTxt = { found: false };
    }
  } catch { checks.robotsTxt = { found: false }; }

  // Check sitemap.xml
  try {
    const r = await fetch(baseUrl + "/sitemap.xml");
    checks.sitemapXml = { found: r.ok, status: r.status };
  } catch { checks.sitemapXml = { found: false }; }

  return checks;
}
