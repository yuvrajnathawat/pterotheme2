import path from "node:path";
import zlib from "node:zlib";
import type { BuildArtifact } from "bun";
import { $ } from "bun";

export async function generateManifest(
    outputs: BuildArtifact[],
    outdir: string,
): Promise<Record<string, any>> {
    const manifest: Record<string, any> = {};
    for (const output of outputs) {
        if (output.path.endsWith(".map")) continue;
        const fileName = path.basename(output.path);
        const publicPath = `/assets/${path.relative(outdir, output.path)}`;

        const contents = await output.arrayBuffer();
        const integrity = `sha384-${new Bun.CryptoHasher("sha384").update(contents).digest("base64")}`;

        if (output.kind === "entry-point") {
            if (fileName.endsWith(".js"))
                manifest["main.js"] = { src: publicPath, integrity };
            else if (fileName.endsWith(".css"))
                manifest["main.css"] = { src: publicPath, integrity };
        }
        if (
            !manifest["main.css"] &&
            fileName.startsWith("index") &&
            fileName.endsWith(".css")
        ) {
            manifest["main.css"] = { src: publicPath, integrity };
        }

        manifest[fileName] = { src: publicPath, integrity, size: output.size };
    }

    // Explicitly tag core shell chunks for prioritized preloading
    const coreShell = ["index.", "App.", "DashboardRouter.", "ServerRouter.", "theme."];
    Object.entries(manifest).forEach(([key, val]) => {
        if (key.endsWith(".js")) {
            coreShell.forEach(prefix => {
                if (key.startsWith(prefix) && (val.size > 2048)) { // > 2KB to ignore tiny bridges
                    manifest[`core.shell.${prefix}js`] = val;
                }
            });
        }
    });

    // Identify top 3 largest remaining JS chunks (likely common libs)
    const sharedChunks = Object.entries(manifest)
        .filter(([key, val]) => key.endsWith(".js") && !key.startsWith("core.") && (val.size > 51200)) // > 50KB
        .sort((a, b) => (b[1].size || 0) - (a[1].size || 0))
        .slice(0, 3);

    sharedChunks.forEach(([key, val], index) => {
        manifest[`core.shared.${index}.js`] = val;
    });

    // Tag auth-related chunks so the server can preload them on /auth/* routes
    const authChunks = ["AuthenticationRouter.", "LoginContainer.", "AuthNavigationBar."];
    Object.entries(manifest).forEach(([key, val]) => {
        if (key.endsWith(".js")) {
            authChunks.forEach(prefix => {
                if (key.startsWith(prefix) && (val.size > 512)) {
                    manifest[`auth.${prefix}js`] = val;
                }
            });
        }
    });

    return manifest;
}

/**
 * Replace CSS import statements in JS files with dynamic <link> tag injection.
 *
 * Webpack uses style-loader to bundle CSS inside JS and inject it at runtime.
 * Bun extracts CSS to separate files and adds `import "chunk.css"` to the JS,
 * but browsers cannot load CSS as a JS module (MIME type error).
 *
 * Instead of stripping the imports entirely (which breaks layout), we replace
 * them with a small snippet that creates a <link rel="stylesheet"> tag, so the
 * CSS file loads when its JS chunk is executed.
 */
export async function replaceCssImports(outputs: BuildArtifact[]) {
    console.log("Replacing CSS imports with <link> tag injection...");

    // Helper: generate a minified IIFE expression that injects a <link rel="stylesheet">
    // and deduplicates by checking if the same href is already in <head>.
    // Returns the IIFE as an expression (no trailing semicolon) so it can be used
    // both standalone and inside Promise.resolve().
    const makeLinkExpr = (href: string) =>
        `(()=>{if(!document.querySelector('link[href="${href}"]')){let l=document.createElement("link");l.rel="stylesheet";l.href="${href}";document.head.appendChild(l)}})()`;

    for (const output of outputs) {
        if (
            (output.kind === "entry-point" || output.kind === "chunk") &&
            output.path.endsWith(".js")
        ) {
            const file = Bun.file(output.path);
            const original = await file.text();
            let content = original;

            // Static CSS imports: import "/assets/chunk.hash.css";
            // Capture the URL so we can inject a <link> for it.
            content = content.replace(
                /import\s*["']([^"']+\.css)["'];?/g,
                (_match, cssUrl) => `${makeLinkExpr(cssUrl)};`
            );

            // Dynamic CSS imports: await import("/assets/chunk.hash.css");
            // Use the expression form (no trailing ;) inside Promise.resolve().
            content = content.replace(
                /import\s*\(\s*["']([^"']+\.css)["']\s*\);?/g,
                (_match, cssUrl) => `Promise.resolve(${makeLinkExpr(cssUrl)});`
            );

            if (content !== original) {
                await Bun.write(output.path, content);
                console.log(`  Injected CSS loaders in ${path.basename(output.path)}`);
            }
        }
    }
}

export async function compressAssets(outputs: BuildArtifact[]) {
    console.log("Compressing assets with Gzip...");
    for (const output of outputs) {
        if (output.path.endsWith(".map")) continue;
        const file = Bun.file(output.path);
        const contents = await file.arrayBuffer();
        const compressed = Bun.gzipSync(new Uint8Array(contents));
        await Bun.write(`${output.path}.gz`, compressed);
    }
}

export async function compressAssetsBrotli(outputs: BuildArtifact[]) {
    console.log("Compressing assets with Brotli...");
    for (const output of outputs) {
        if (output.path.endsWith(".map")) continue;
        const file = Bun.file(output.path);
        const contents = await file.arrayBuffer();
        const compressed = zlib.brotliCompressSync(Buffer.from(contents), {
            params: {
                [zlib.constants.BROTLI_PARAM_QUALITY]: 11,
            },
        });
        await Bun.write(`${output.path}.br`, compressed);
    }
}

export async function createArchive() {
    console.log("Creating archive...");
    await $`tar -czvf panel.tar.gz public/assets`;
}

export async function copyStaticAssets(outdir: string) {
    console.log("Copying static assets...");

    // Ensure directories exist
    await $`mkdir -p ${path.join(outdir, "css")}`;
    await $`mkdir -p ${path.join(outdir, "svgs")}`;
    await $`mkdir -p ${path.join(outdir, "fonts")}`;

    // Copy hyper.css and fonts.css
    const hyperCssPath = "rolexdev/themes/hyperv1/assets/css/hyper.css";
    const fontsCssPath = "rolexdev/themes/hyperv1/assets/css/fonts.css";
    await $`cp ${hyperCssPath} ${path.join(outdir, "css", "hyper.css")}`;
    await $`cp ${fontsCssPath} ${path.join(outdir, "css", "fonts.css")}`;

    // Copy fonts recursively
    const fontsSource = "rolexdev/themes/hyperv1/assets/fonts";
    const fontsDest = path.join(outdir, "fonts");
    // cp -rv will recursively copy the directory structure, handling inner folders like 'selector'
    await $`cp -rv ${fontsSource}/* ${fontsDest}`;

    // Copy SVGs
    // We want specifically the ones from resources/scripts/assets/images
    // Note: The user mentioned "public/assets/svgs" has files like server_installing.svg
    // which we located in resources/scripts/assets/images/
    const svgSource = "resources/scripts/assets/images/*.svg";
    const svgDest = path.join(outdir, "svgs");

    // Use sh -c to handle glob expansion correctly if needed, but bun's $ should handle it if passed as string
    // However, explicit globbing with shell is safer for wildcards in $
    await $`cp ${process.cwd()}/resources/scripts/assets/images/*.svg ${svgDest}`;

    // Copy Monaco Editor
    console.log("Copying Monaco Editor assets...");
    const monacoSource = "node_modules/monaco-editor/min/vs";
    const monacoDest = path.join(outdir, "monaco");
    await $`mkdir -p ${monacoDest}`;
    await $`cp -r ${monacoSource} ${monacoDest}/`;
}

export function showSummary(
    outputs: BuildArtifact[],
    useCompress: boolean,
    useBrotli: boolean,
) {
    const validOutputs = outputs.filter((o) => o.size > 0 && !o.path.endsWith(".map"));

    const outputSummary = validOutputs.map((o) => {
        const fileName = path.basename(o.path);
        const size = `${(o.size / 1024).toFixed(2)} KB`;
        let gzippedSize = "-";
        let brotliSize = "-";

        if (useCompress) {
            const gzFile = Bun.file(`${o.path}.gz`);
            if (gzFile.size > 0) {
                gzippedSize = `${(gzFile.size / 1024).toFixed(2)} KB`;
            }
        }

        if (useBrotli) {
            const brFile = Bun.file(`${o.path}.br`);
            if (brFile.size > 0) {
                brotliSize = `${(brFile.size / 1024).toFixed(2)} KB`;
            }
        }

        return {
            File: fileName,
            Size: size,
            ...(useCompress ? { "Gzip Size": gzippedSize } : {}),
            ...(useBrotli ? { "Brotli Size": brotliSize } : {}),
        };
    });

    console.table(outputSummary);

    if (useCompress || useBrotli) {
        const categories: Record<string, { original: number; gzip: number; brotli: number }> = {};

        for (const o of validOutputs) {
            const ext = path.extname(o.path).slice(1).toUpperCase() || "OTHER";
            let category = ext;

            if (ext === "JS") {
                category = o.kind === "entry-point" ? "JS (Main)" : "JS (Chunk)";
            }

            if (!categories[category]) {
                categories[category] = { original: 0, gzip: 0, brotli: 0 };
            }

            categories[category].original += o.size;
            if (useCompress) {
                const gzFile = Bun.file(`${o.path}.gz`);
                categories[category].gzip += gzFile.size;
            }
            if (useBrotli) {
                const brFile = Bun.file(`${o.path}.br`);
                categories[category].brotli += brFile.size;
            }
        }

        console.log("\n--- Compression Summary ---");

        const sortedCategories = Object.keys(categories).sort((a, b) => {
            if (a.startsWith("JS (Main)")) return -1;
            if (b.startsWith("JS (Main)")) return 1;
            if (a.startsWith("JS (Chunk)")) return -1;
            if (b.startsWith("JS (Chunk)")) return 1;
            return a.localeCompare(b);
        });
        const formatSize = (bytes: number) => (bytes / 1024).toFixed(2) + " KB";

        if (useCompress) {
            console.log("\n-- Gzip " + "-".repeat(40));
            for (const cat of sortedCategories) {
                const data = categories[cat];
                const ratio = ((1 - data.gzip / data.original) * 100).toFixed(1);
                console.log(`${cat.padEnd(10)} : ${formatSize(data.original).padStart(10)} -> ${formatSize(data.gzip).padStart(10)} (${ratio}% reduction)`);
            }
        }

        if (useBrotli) {
            console.log("\n-- Brotli " + "-".repeat(40));
            for (const cat of sortedCategories) {
                const data = categories[cat];
                const ratio = ((1 - data.brotli / data.original) * 100).toFixed(1);
                console.log(`${cat.padEnd(10)} : ${formatSize(data.original).padStart(10)} -> ${formatSize(data.brotli).padStart(10)} (${ratio}% reduction)`);
            }
        }
    }
}
