/**
 * Frontend bootstrap for theme switching, navigation filtering, and media popovers.
 */

const body = document.body;
const root = document.documentElement;
const toggle = document.querySelector("[data-sidebar-toggle]");
const closeLayer = document.querySelector("[data-sidebar-close]");
const searchInput = document.querySelector("[data-nav-search]");
const themeSelect = document.querySelector("[data-theme-select]");
const themeHint = document.querySelector("[data-theme-hint]");
const activeLink = document.querySelector(".nav-link.is-active, .nav-group__overview.is-active");
const themeSettings = window.__CMS_THEME_SETTINGS || {};
const themeCookiePath = themeSelect && themeSelect.dataset.themeCookiePath
    ? themeSelect.dataset.themeCookiePath
    : (themeSettings.cookiePath || "/");
const themeStorageKey = themeSelect && themeSelect.dataset.themeStorageKey
    ? themeSelect.dataset.themeStorageKey
    : "enari-cms-theme";
const systemThemeQuery = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

let applyThemeSelection = null;

if (themeSelect) {
    const themeOptions = themeSettings.themeConfig && typeof themeSettings.themeConfig === "object"
        ? themeSettings.themeConfig
        : Array.from(themeSelect.options).reduce((options, option) => {
            if (option.value === "system") {
                return options;
            }

            options[option.value] = {
                description: option.dataset.description || "",
                scheme: option.dataset.scheme || "light",
                layout: option.dataset.layout || "folio",
                tokens: {},
            };

            return options;
        }, {});
    const themeTokenDefaults = themeSettings.themeTokenDefaults && typeof themeSettings.themeTokenDefaults === "object"
        ? themeSettings.themeTokenDefaults
        : {};
    const themeAssets = themeSettings.themeAssets && typeof themeSettings.themeAssets === "object"
        ? themeSettings.themeAssets
        : {};
    /**
     * Applies theme tokens.
     */
    const applyThemeTokens = (tokens = {}) => {
        const mergedTokens = {
            ...themeTokenDefaults,
            ...tokens,
        };

        Object.entries(mergedTokens).forEach(([key, value]) => {
            const datasetKey = `theme${key.charAt(0).toUpperCase()}${key.slice(1)}`;
            if (typeof value === "string" && value !== "") {
                root.dataset[datasetKey] = value;
            }
        });
    };

    const defaultLightTheme = themeOptions[themeSelect.dataset.defaultLight]
        ? themeSelect.dataset.defaultLight
        : "parchment";
    const defaultDarkTheme = themeOptions[themeSelect.dataset.defaultDark]
        ? themeSelect.dataset.defaultDark
        : "midnight";

    /**
     * Resolves theme.
     */
    const resolveTheme = (selectedTheme) => {
        if (selectedTheme === "system") {
            const prefersDark = systemThemeQuery ? systemThemeQuery.matches : false;
            return prefersDark ? defaultDarkTheme : defaultLightTheme;
        }

        return themeOptions[selectedTheme] ? selectedTheme : defaultLightTheme;
    };

    /**
     * Processes theme has assets.
     */
    const themeHasAssets = (themeKey) => {
        const themeAssetEntry = themeKey && themeAssets[themeKey] && typeof themeAssets[themeKey] === "object"
            ? themeAssets[themeKey]
            : null;
        if (!themeAssetEntry) {
            return false;
        }

        const stylesheets = Array.isArray(themeAssetEntry.stylesheets) ? themeAssetEntry.stylesheets : [];
        const scripts = Array.isArray(themeAssetEntry.scripts) ? themeAssetEntry.scripts : [];
        return stylesheets.length > 0 || scripts.length > 0;
    };

    /**
     * Determines whether reload for theme assets.
     */
    const shouldReloadForThemeAssets = (previousResolvedTheme, nextResolvedTheme) => {
        if (!previousResolvedTheme || !nextResolvedTheme || previousResolvedTheme === nextResolvedTheme) {
            return false;
        }

        return themeHasAssets(previousResolvedTheme) || themeHasAssets(nextResolvedTheme);
    };

    applyThemeSelection = (selectedTheme, { persist = false } = {}) => {
        const normalizedTheme = themeOptions[selectedTheme] ? selectedTheme : "system";
        const previousResolvedTheme = root.dataset.themeResolved || resolveTheme(root.dataset.themeSelected || "system");
        const resolvedTheme = resolveTheme(normalizedTheme);
        const resolvedOption = themeOptions[resolvedTheme] || themeOptions[defaultLightTheme];
        const resolvedScheme = resolvedOption && resolvedOption.scheme === "dark" ? "dark" : "light";
        const resolvedLayout = resolvedOption && resolvedOption.layout ? resolvedOption.layout : "folio";
        const previousLayout = root.dataset.themeLayout || "folio";

        root.dataset.themeSelected = normalizedTheme;
        root.dataset.themeResolved = resolvedTheme;
        root.dataset.themeLayout = resolvedLayout;
        applyThemeTokens(resolvedOption ? resolvedOption.tokens : {});
        root.style.colorScheme = resolvedScheme;

        if (themeSelect.value !== normalizedTheme) {
            themeSelect.value = normalizedTheme;
        }

        if (themeHint) {
            if (normalizedTheme === "system") {
                const resolvedDescription = resolvedOption ? resolvedOption.description : "";
                themeHint.textContent = resolvedDescription !== ""
                    ? `Systemmodus aktiv. Aktuell: ${resolvedDescription}`
                    : "Systemmodus aktiv.";
            } else {
                themeHint.textContent = themeOptions[normalizedTheme].description || "";
            }
        }

        document.dispatchEvent(new CustomEvent("cms:themechange", {
            detail: {
                selectedTheme: normalizedTheme,
                resolvedTheme,
                resolvedLayout,
                resolvedScheme,
            },
        }));

        if (persist) {
            try {
                window.localStorage.setItem(themeStorageKey, normalizedTheme);
            } catch (error) {
                // Ignore storage errors and keep the in-memory selection.
            }

            document.cookie = `${encodeURIComponent(themeStorageKey)}=${encodeURIComponent(normalizedTheme)}; path=${themeCookiePath}; max-age=31536000; samesite=lax`;

            if (previousLayout !== resolvedLayout || shouldReloadForThemeAssets(previousResolvedTheme, resolvedTheme)) {
                window.location.reload();
            }
        }
    };

    let initialTheme = root.dataset.themeSelected || "system";
    const serverTheme = root.dataset.themeServerSelected || root.dataset.themeSelected || "system";
    const serverResolvedTheme = root.dataset.themeServerResolved || resolveTheme(serverTheme);
    const serverLayout = root.dataset.themeServerLayout || root.dataset.themeLayout || "folio";
    if (!themeOptions[initialTheme]) {
        initialTheme = "system";
        try {
            const storedTheme = window.localStorage.getItem(themeStorageKey);
            if (storedTheme && themeOptions[storedTheme]) {
                initialTheme = storedTheme;
            }
        } catch (error) {
            initialTheme = "system";
        }
    }

    applyThemeSelection(initialTheme);

    const resolvedThemeAfterInit = root.dataset.themeResolved || resolveTheme(initialTheme);
    const layoutAfterInit = root.dataset.themeLayout || "folio";
    const serverThemeMismatch = initialTheme !== serverTheme;
    const serverResolvedMismatch = resolvedThemeAfterInit !== serverResolvedTheme;
    const serverLayoutMismatch = layoutAfterInit !== serverLayout;

    if (serverThemeMismatch || serverResolvedMismatch || serverLayoutMismatch) {
        document.cookie = `${encodeURIComponent(themeStorageKey)}=${encodeURIComponent(initialTheme)}; path=${themeCookiePath}; max-age=31536000; samesite=lax`;
        if (serverLayoutMismatch || shouldReloadForThemeAssets(serverResolvedTheme, resolvedThemeAfterInit)) {
            window.location.reload();
        }
    }

    themeSelect.addEventListener("change", () => {
        applyThemeSelection(themeSelect.value, { persist: true });
    });

    if (systemThemeQuery) {
        /**
         * Synchronizes system theme.
         */
        const syncSystemTheme = () => {
            if (root.dataset.themeSelected === "system" && applyThemeSelection) {
                const previousLayout = root.dataset.themeLayout || "folio";
                const previousResolvedTheme = root.dataset.themeResolved || resolveTheme("system");
                applyThemeSelection("system");
                if ((root.dataset.themeLayout || "folio") !== previousLayout
                    || shouldReloadForThemeAssets(previousResolvedTheme, root.dataset.themeResolved || resolveTheme("system"))) {
                    window.location.reload();
                }
            }
        };

        if (typeof systemThemeQuery.addEventListener === "function") {
            systemThemeQuery.addEventListener("change", syncSystemTheme);
        } else if (typeof systemThemeQuery.addListener === "function") {
            systemThemeQuery.addListener(syncSystemTheme);
        }
    }
}

if (toggle) {
    toggle.addEventListener("click", () => {
        const expanded = toggle.getAttribute("aria-expanded") === "true";
        toggle.setAttribute("aria-expanded", expanded ? "false" : "true");
        body.classList.toggle("sidebar-open", !expanded);
    });
}

if (closeLayer) {
    closeLayer.addEventListener("click", () => {
        body.classList.remove("sidebar-open");
        if (toggle) {
            toggle.setAttribute("aria-expanded", "false");
        }
    });
}

document.querySelectorAll(".nav-group__overview").forEach((link) => {
    link.addEventListener("click", (event) => {
        event.stopPropagation();
    });
});

if (activeLink) {
    requestAnimationFrame(() => {
        activeLink.scrollIntoView({
            block: "center",
            inline: "nearest",
        });
    });
}

if (searchInput) {
    const rootList = document.querySelector(".tree > .nav-list");

    /**
     * Filters list.
     */
    const filterList = (list, query) => {
        let hasVisibleItems = false;

        Array.from(list.children).forEach((item) => {
            /**
             * Processes own text.
             */
            const ownText = (item.dataset.searchText || "").toLowerCase();
            const nestedList = item.querySelector(":scope > details > .nav-list--nested");
            let hasVisibleChildren = false;

            if (nestedList) {
                hasVisibleChildren = filterList(nestedList, query);
            }

            const isVisible = query === "" || ownText.includes(query) || hasVisibleChildren;
            item.hidden = !isVisible;
            hasVisibleItems = hasVisibleItems || isVisible;

            const details = item.querySelector(":scope > details");
            if (details) {
                const isActive = details.dataset.active === "true";
                details.open = query !== "" ? isVisible : isActive;
            }
        });

        return hasVisibleItems;
    };

    searchInput.addEventListener("input", () => {
        if (!rootList) {
            return;
        }

        filterList(rootList, searchInput.value.trim().toLowerCase());
    });
}

const popoverTriggers = Array.from(document.querySelectorAll("[data-media-popover-trigger]"));

/**
 * Creates media popover.
 */
const createMediaPopover = () => {
    const dialog = document.createElement("dialog");
    dialog.className = "media-popover";
    dialog.setAttribute("aria-label", "Medienansicht");

    const inner = document.createElement("div");
    inner.className = "media-popover__inner";

    const header = document.createElement("div");
    header.className = "media-popover__header";

    const title = document.createElement("p");
    title.className = "media-popover__title";
    title.hidden = true;

    const closeButton = document.createElement("button");
    closeButton.type = "button";
    closeButton.className = "media-popover__close";
    closeButton.textContent = "Schliessen";

    const content = document.createElement("div");
    content.className = "media-popover__content";

    header.append(title, closeButton);
    inner.append(header, content);
    dialog.append(inner);
    document.body.append(dialog);

    /**
     * Clears content.
     */
    const clearContent = () => {
        const activeVideo = content.querySelector("video");
        if (activeVideo) {
            activeVideo.pause();
        }

        content.replaceChildren();
        title.textContent = "";
        title.hidden = true;
    };

    closeButton.addEventListener("click", () => {
        dialog.close();
    });

    dialog.addEventListener("click", (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener("close", clearContent);

    return {
        dialog,
        title,
        content,
    };
};

/**
 * Builds popover content.
 */
const buildPopoverContent = (kind, src, title) => {
    if (kind === "image") {
        const image = document.createElement("img");
        image.src = src;
        image.alt = title || "";
        image.loading = "eager";
        return image;
    }

    if (kind === "video") {
        const video = document.createElement("video");
        video.controls = true;
        video.preload = "metadata";
        video.src = src;
        return video;
    }

    if (kind === "pdf") {
        const frame = document.createElement("iframe");
        frame.src = src;
        frame.loading = "eager";
        frame.title = title || "PDF-Dokument";
        return frame;
    }

    return null;
};

if (popoverTriggers.length > 0) {
    const mediaPopover = createMediaPopover();

    popoverTriggers.forEach((trigger) => {
        trigger.addEventListener("click", (event) => {
            if (typeof mediaPopover.dialog.showModal !== "function") {
                return;
            }

            const { mediaSrc, mediaKind, mediaTitle } = trigger.dataset;
            if (!mediaSrc || !mediaKind) {
                return;
            }

            const popoverNode = buildPopoverContent(mediaKind, mediaSrc, mediaTitle || "");
            if (!popoverNode) {
                return;
            }

            event.preventDefault();
            mediaPopover.content.replaceChildren(popoverNode);
            mediaPopover.title.textContent = mediaTitle || "";
            mediaPopover.title.hidden = (mediaTitle || "") === "";

            if (!mediaPopover.dialog.open) {
                mediaPopover.dialog.showModal();
            }
        });
    });
}
