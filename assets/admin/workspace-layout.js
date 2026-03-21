/**
 * Accessibility and workspace navigation layer for the admin UI.
 */
(() => {
    const app = document.querySelector("[data-admin-app]");

    if (!app) {
        return;
    }

    const liveRegion = document.querySelector("[data-admin-live]");
    const workspaceButtons = Array.from(document.querySelectorAll("[data-admin-workspace-button]"));
    const workspacePanels = Array.from(document.querySelectorAll("[data-admin-workspace-panel]"));
    const tabLists = Array.from(document.querySelectorAll("[data-admin-tablist]"));
    const sidebar = document.querySelector("[data-admin-sidebar]");
    const sidebarToggle = document.querySelector("[data-admin-sidebar-toggle]");
    const sidebarClose = document.querySelector("[data-admin-sidebar-close]");
    const sidebarOverlay = document.querySelector("[data-admin-sidebar-overlay]");
    const sidebarFilter = document.querySelector("[data-admin-filter]");

    const defaultTabs = {
        editor: "markdown",
        review: "preview",
        git: "status",
    };

    const activeTabs = { ...defaultTabs };
    let liveTimer = 0;

    /**
     * Returns focusable descendants of a container.
     */
    const getFocusable = (container) => Array.from(container.querySelectorAll(
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter((element) => !element.hasAttribute("hidden"));

    /**
     * Announces a status update through the live region.
     */
    const announce = (message) => {
        if (!liveRegion || !message) {
            return;
        }

        window.clearTimeout(liveTimer);
        liveRegion.textContent = "";
        liveTimer = window.setTimeout(() => {
            liveRegion.textContent = message;
        }, 25);
    };

    /**
     * Parses the current hash into workspace and tab state.
     */
    const parseHashState = () => {
        const params = new URLSearchParams(window.location.hash.replace(/^#/, ""));
        return {
            workspace: params.get("workspace") || "library",
            tab: params.get("tab") || "",
        };
    };

    /**
     * Writes the active workspace and tab into the URL hash.
     */
    const writeHashState = (workspace, tab) => {
        const params = new URLSearchParams();
        params.set("workspace", workspace);
        if (tab) {
            params.set("tab", tab);
        }
        const nextHash = `#${params.toString()}`;
        if (window.location.hash !== nextHash) {
            window.history.replaceState(null, "", nextHash);
        }
    };

    /**
     * Resolves the tab buttons for a workspace.
     */
    const getWorkspaceTabs = (workspace) => Array.from(document.querySelectorAll(`[data-admin-tab^="${workspace}:"]`));

    /**
     * Resolves the tab panels for a workspace.
     */
    const getWorkspaceTabPanels = (workspace) => Array.from(document.querySelectorAll(`[data-admin-tab-panel^="${workspace}:"]`));

    /**
     * Returns the currently selected tab id for a workspace.
     */
    const resolveTabId = (workspace, requestedTab = "") => {
        const tabs = getWorkspaceTabs(workspace);
        if (!tabs.length) {
            return "";
        }

        const candidate = requestedTab || activeTabs[workspace] || defaultTabs[workspace] || "";
        const matching = tabs.find((button) => (button.dataset.adminTab || "") === `${workspace}:${candidate}`);
        return matching ? candidate : (tabs[0].dataset.adminTab || "").split(":")[1] || "";
    };

    /**
     * Applies the current workspace and tab visibility state.
     */
    const applyWorkspaceState = (workspace, requestedTab = "", { updateHash = true } = {}) => {
        const workspaceId = workspaceButtons.some((button) => button.dataset.adminWorkspaceButton === workspace)
            ? workspace
            : "library";
        const tabId = resolveTabId(workspaceId, requestedTab);

        workspaceButtons.forEach((button) => {
            const isActive = button.dataset.adminWorkspaceButton === workspaceId;
            button.classList.toggle("is-active", isActive);
            if (isActive) {
                button.setAttribute("aria-current", "page");
                button.tabIndex = 0;
            } else {
                button.removeAttribute("aria-current");
                button.tabIndex = -1;
            }
        });

        workspacePanels.forEach((panel) => {
            panel.hidden = panel.dataset.adminWorkspacePanel !== workspaceId;
        });

        if (tabId) {
            activeTabs[workspaceId] = tabId;
        }

        getWorkspaceTabs(workspaceId).forEach((button) => {
            const isActive = (button.dataset.adminTab || "") === `${workspaceId}:${tabId}`;
            button.classList.toggle("is-active", isActive);
            button.setAttribute("aria-selected", isActive ? "true" : "false");
            button.tabIndex = isActive ? 0 : -1;
        });

        getWorkspaceTabPanels(workspaceId).forEach((panel) => {
            panel.hidden = panel.dataset.adminTabPanel !== `${workspaceId}:${tabId}`;
        });

        if (updateHash) {
            writeHashState(workspaceId, tabId);
        }

        app.dispatchEvent(new CustomEvent("cms-admin:workspace-change", {
            detail: {
                workspace: workspaceId,
                tab: tabId,
            },
        }));
    };

    /**
     * Opens the document drawer on narrow screens.
     */
    const openSidebar = () => {
        if (!sidebar) {
            return;
        }

        app.classList.add("is-sidebar-open");
        sidebar.hidden = false;
        if (sidebarOverlay) {
            sidebarOverlay.hidden = false;
            sidebarOverlay.setAttribute("aria-hidden", "false");
        }
        if (sidebarToggle) {
            sidebarToggle.setAttribute("aria-expanded", "true");
        }
        window.requestAnimationFrame(() => {
            if (sidebarFilter instanceof HTMLElement) {
                sidebarFilter.focus();
            }
        });
    };

    /**
     * Closes the document drawer.
     */
    const closeSidebar = () => {
        if (!sidebar) {
            return;
        }

        app.classList.remove("is-sidebar-open");
        if (sidebarOverlay) {
            sidebarOverlay.hidden = true;
            sidebarOverlay.setAttribute("aria-hidden", "true");
        }
        if (sidebarToggle) {
            sidebarToggle.setAttribute("aria-expanded", "false");
        }
    };

    /**
     * Traps focus within an interactive overlay.
     */
    const trapFocus = (event, container) => {
        if (event.key !== "Tab") {
            return;
        }

        const focusable = getFocusable(container);
        if (!focusable.length) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };

    /**
     * Enhances a modal dialog with focus trapping and restoration.
     */
    const enhanceModal = (dialog, closeModal) => {
        const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        dialog.tabIndex = -1;

        const handleKeydown = (event) => {
            if (event.key === "Escape") {
                event.preventDefault();
                closeModal();
                return;
            }

            trapFocus(event, dialog);
        };

        dialog.addEventListener("keydown", handleKeydown);
        window.requestAnimationFrame(() => {
            const focusable = getFocusable(dialog);
            (focusable[0] || dialog).focus();
        });

        return () => {
            dialog.removeEventListener("keydown", handleKeydown);
            if (previousFocus && previousFocus.isConnected) {
                previousFocus.focus();
            }
        };
    };

    /**
     * Wires arrow-key navigation for a linear button group.
     */
    const bindLinearNavigation = (buttons, activate) => {
        buttons.forEach((button, index) => {
            button.addEventListener("keydown", (event) => {
                if (!["ArrowLeft", "ArrowRight", "Home", "End", "ArrowUp", "ArrowDown"].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                let nextIndex = index;
                if (event.key === "Home") {
                    nextIndex = 0;
                } else if (event.key === "End") {
                    nextIndex = buttons.length - 1;
                } else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
                    nextIndex = (index - 1 + buttons.length) % buttons.length;
                } else if (event.key === "ArrowRight" || event.key === "ArrowDown") {
                    nextIndex = (index + 1) % buttons.length;
                }

                const nextButton = buttons[nextIndex];
                nextButton.focus();
                activate(nextButton);
            });
        });
    };

    workspaceButtons.forEach((button) => {
        button.addEventListener("click", () => {
            applyWorkspaceState(button.dataset.adminWorkspaceButton || "editor");
        });
    });
    bindLinearNavigation(workspaceButtons, (button) => {
        applyWorkspaceState(button.dataset.adminWorkspaceButton || "editor");
    });

    tabLists.forEach((tabList) => {
        const workspace = tabList.dataset.adminTablist || "";
        const buttons = getWorkspaceTabs(workspace);
        buttons.forEach((button) => {
            button.addEventListener("click", () => {
                applyWorkspaceState(workspace, (button.dataset.adminTab || "").split(":")[1] || "");
            });
        });
        bindLinearNavigation(buttons, (button) => {
            applyWorkspaceState(workspace, (button.dataset.adminTab || "").split(":")[1] || "");
        });
    });

    if (sidebarToggle) {
        sidebarToggle.addEventListener("click", () => {
            if (app.classList.contains("is-sidebar-open")) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener("click", closeSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener("click", closeSidebar);
    }

    if (sidebar) {
        sidebar.addEventListener("keydown", (event) => {
            if (!app.classList.contains("is-sidebar-open")) {
                return;
            }

            if (event.key === "Escape") {
                event.preventDefault();
                closeSidebar();
                if (sidebarToggle instanceof HTMLElement) {
                    sidebarToggle.focus();
                }
                return;
            }

            trapFocus(event, sidebar);
        });
    }

    window.addEventListener("hashchange", () => {
        const { workspace, tab } = parseHashState();
        applyWorkspaceState(workspace, tab, { updateHash: false });
    });

    const initialState = parseHashState();
    applyWorkspaceState(initialState.workspace, initialState.tab, { updateHash: false });

    window.CMSAdminWorkspaceLayout = {
        announce,
        closeSidebar,
        openSidebar,
        enhanceModal,
    };
})();
