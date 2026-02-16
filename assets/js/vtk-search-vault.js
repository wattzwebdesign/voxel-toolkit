/**
 * VTK Search Vault — Unified JS for Save, Load, and Manage saved searches
 */
(function () {
  "use strict";

  /* ──────────────────────────────────────────
     Shared: VtkDialog component
  ────────────────────────────────────────── */
  var VtkDialog = {
    template: "#vtk-dialog-tpl",
    props: {
      visible: { type: Boolean, default: false },
      title: { type: String, default: "" },
      size: { type: String, default: "sm" },
    },
    emits: ["close"],
    methods: {
      onOverlayClick: function () {
        this.$emit("close");
      },
      onEsc: function (e) {
        if (e.key === "Escape") this.$emit("close");
      },
    },
    mounted: function () {
      document.addEventListener("keydown", this.onEsc);
    },
    beforeUnmount: function () {
      document.removeEventListener("keydown", this.onEsc);
    },
  };

  /* ──────────────────────────────────────────
     Shared: renderCriterion(filter)
  ────────────────────────────────────────── */
  function renderCriterion(filter) {
    if (!filter || filter.value === null || filter.value === undefined) {
      return filter && filter.props ? filter.props.placeholder || "" : "";
    }
    var v = filter.value;
    var t = filter.type;

    switch (t) {
      case "keywords":
      case "stepper":
      case "switcher":
      case "post-status":
      case "date":
      case "recurring-date":
      case "availability":
        return String(v);

      case "range":
        if (typeof v === "object") {
          var mn = v.min || "";
          var mx = v.max || "";
          if (mn && mx) return mn + " \u2013 " + mx;
          if (mn) return "From " + mn;
          if (mx) return "Up to " + mx;
        }
        return String(v);

      case "location":
        if (typeof v === "string") {
          return v.split(";")[0] || v;
        }
        if (typeof v === "object" && v.address) return v.address;
        return (filter.props && filter.props.placeholder) || "";

      case "terms":
        if (Array.isArray(v)) {
          return v
            .map(function (x) {
              return x.label || x;
            })
            .filter(Boolean)
            .join(", ");
        }
        if (typeof v === "object" && v.label) return v.label;
        return String(v);

      case "user":
      case "relations":
        if (Array.isArray(v)) {
          return v
            .map(function (x) {
              return x.title || x.label || x;
            })
            .filter(Boolean)
            .join(", ");
        }
        if (typeof v === "object") return v.title || v.label || "";
        return String(v);

      default:
        if (Array.isArray(v)) return v.join(", ");
        if (typeof v === "object") return JSON.stringify(v);
        return String(v);
    }
  }

  /* ──────────────────────────────────────────
     Shared: buildSearchUrl(search)
  ────────────────────────────────────────── */
  function buildSearchUrl(search) {
    if (!search || !search.params) return "#";
    var params = Object.assign({}, search.params);
    var ordered = {};

    if (params.post_type) {
      ordered.type = params.post_type;
      delete params.post_type;
    }

    Object.keys(params).forEach(function (key) {
      var val = params[key];
      if (val === null || val === undefined) return;
      if (Array.isArray(val)) {
        val = val.join(",");
      } else if (typeof val === "object") {
        return;
      }
      ordered[key] = val;
    });

    var parts = [];
    Object.keys(ordered).forEach(function (key) {
      var v = String(ordered[key]);
      var enc = encodeURIComponent(v)
        .replace(/%2C/g, ",")
        .replace(/%3B/g, ";")
        .replace(/%3A/g, ":")
        .replace(/%2B/g, "+")
        .replace(/%20/g, "+");
      parts.push(key + "=" + enc);
    });

    var qs = parts.join("&");
    var base =
      (search.post_type && search.post_type.archive_link) ||
      window.location.pathname;
    return qs.length ? base + "?" + qs : base;
  }

  /* ──────────────────────────────────────────
     Shared: formatDate helper
  ────────────────────────────────────────── */
  function formatDate(dateString) {
    if (!dateString) return "";
    try {
      var d = new Date(dateString);
      if (isNaN(d.getTime())) return dateString;
      return d.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    } catch (e) {
      return dateString;
    }
  }

  /* ──────────────────────────────────────────
     Shared: clipboard copy helper
  ────────────────────────────────────────── */
  function copyToClipboard(text, successMsg) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard
        .writeText(text)
        .then(function () {
          Voxel.alert(successMsg, "success", [], 4000);
        })
        .catch(function () {
          fallbackCopy(text, successMsg);
        });
    } else {
      fallbackCopy(text, successMsg);
    }
  }

  function fallbackCopy(text, successMsg) {
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.style.position = "fixed";
    ta.style.opacity = "0";
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand("copy");
      Voxel.alert(successMsg, "success", [], 4000);
    } catch (e) {
      Voxel.alert("Failed to copy link", "error");
    }
    document.body.removeChild(ta);
  }

  /* ══════════════════════════════════════════
     PART 1 — Search Form Integration (Save + Load)
  ══════════════════════════════════════════ */
  document.addEventListener("voxel/search-form/init", function (e) {
    var app = e.detail.app;
    var el = e.detail.el;

    var configEl = el
      .closest(".elementor-element")
      ?.querySelector(".vtSavedSearchConfig");
    if (!configEl) return;

    var cfg;
    try {
      cfg = JSON.parse(configEl.innerHTML);
    } catch (err) {
      return;
    }

    /* ---- Inject modal styles ---- */
    if (cfg.modalCss && !document.querySelector('.vtk-modal-styles')) {
      var styleEl = document.createElement('style');
      styleEl.className = 'vtk-modal-styles';
      styleEl.textContent = cfg.modalCss;
      document.head.appendChild(styleEl);
    }

    /* ---- Inject mount points ---- */
    var wrapper = el.querySelector("form .ts-filter-wrapper.flexify");
    if (cfg.enable && wrapper && !wrapper.querySelector(".vtk-trigger--save")) {
      var saveEl = document.createElement("vtk-vault-trigger");
      saveEl.setAttribute("mode", "save");
      wrapper.appendChild(saveEl);
    }
    if (
      cfg.enableLoadSearch &&
      wrapper &&
      !wrapper.querySelector(".vtk-trigger--load")
    ) {
      var loadEl = document.createElement("vtk-vault-trigger");
      loadEl.setAttribute("mode", "load");
      wrapper.appendChild(loadEl);
    }

    /* ---- Register shared dialog component ---- */
    app.component("vtk-dialog", VtkDialog);

    /* ---- VtkVaultTrigger component ---- */
    app.component("vtk-vault-trigger", {
      template: "#vtk-vault-trigger-tpl",
      props: {
        mode: { type: String, default: "save" },
      },
      data: function () {
        return {
          config: cfg,
          showSaveModal: false,
          showLoadModal: false,
          saveTitle: "",
          saveNotification: true,
          saving: false,
          searches: [],
          loadingSearches: false,
          searchesLoaded: false,
          searchQuery: "",
          activeSearchId: null,
          activeSearchTitle: null,
        };
      },
      computed: {
        isSave: function () {
          return this.mode === "save";
        },
        isLoad: function () {
          return this.mode === "load";
        },
        widgetId: function () {
          return this.config.widgetId || "vtk";
        },
        breakpoint: function () {
          return this.$root.breakpoint;
        },
        showTopBtn: function () {
          if (this.isSave) return this.config.showTopPopupButton?.[this.breakpoint];
          var has = this.config.userHasSearches;
          return has && this.config.showLoadTopPopupButton?.[this.breakpoint];
        },
        showMainBtn: function () {
          if (this.isSave) return this.config.showMainButton?.[this.breakpoint];
          var has = this.config.userHasSearches;
          return has && this.config.showLoadMainButton?.[this.breakpoint];
        },
        filteredSearches: function () {
          var self = this;
          var ptKey = this.$root.post_type?.key;
          var list = this.searches.filter(function (s) {
            return s.post_type?.id === ptKey;
          });
          if (!this.searchQuery) return list;
          var q = this.searchQuery.toLowerCase();
          return list.filter(function (s) {
            return (s.title || "Saved Search").toLowerCase().indexOf(q) !== -1;
          });
        },
        hasActiveSearch: function () {
          return this.activeSearchId !== null;
        },
        storageKey: function () {
          return "vtk_active_" + (this.$root.post_type?.key || "default");
        },
      },
      mounted: function () {
        /* Move top-popup icon button into Voxel popup controller */
        var portalSel =
          ".ts-search-portal-" +
          this.config.widgetId +
          " .ts-popup-controller > ul";
        var popupActions = document.querySelector(portalSel);
        if (popupActions && this.$refs.topBtn) {
          var lastLi = popupActions.querySelector("li:last-child");
          try {
            if (lastLi) popupActions.insertBefore(this.$refs.topBtn, lastLi);
            else popupActions.appendChild(this.$refs.topBtn);
          } catch (e) {
            /* silently fail */
          }
        }

        /* Auto-apply for load mode */
        if (this.isLoad && this.config.autoApply) {
          this.checkAutoApply();
        }
      },
      methods: {
        /* ── Save methods ── */
        onSaveClick: function () {
          if (!Voxel_Config.is_logged_in) return Voxel.authRequired();
          if (this.config.askForTitle) {
            this.showSaveModal = true;
            var self = this;
            this.$nextTick(function () {
              var inp = document.querySelector(".vtk-dialog__save-input");
              if (inp) inp.focus();
            });
          } else {
            this.doSave("");
          }
        },
        closeSaveModal: function () {
          this.showSaveModal = false;
          this.saveTitle = "";
          this.saveNotification = true;
          this.saving = false;
        },
        confirmSave: function () {
          this.doSave(this.saveTitle);
        },
        doSave: function (title) {
          if (this.saving) return;
          this.saving = true;
          var self = this;
          jQuery
            .post(Voxel_Config.ajax_url + "&action=vt_save_search", {
              title: title,
              details: Object.assign({}, this.$root.currentValues, {
                post_type: this.$root.post_type.key,
              }),
            })
            .always(function (response) {
              self.saving = false;
              self.closeSaveModal();
              if (response.success) {
                Voxel.alert(
                  self.config.successMessage,
                  "success",
                  [
                    {
                      link: self.config.link,
                      label: self.config.linkLabel,
                    },
                  ],
                  7500
                );
              } else {
                Voxel.alert(
                  response.message || "Error saving search",
                  "error"
                );
              }
            });
        },

        /* ── Load methods ── */
        onLoadClick: function () {
          if (!Voxel_Config.is_logged_in) return Voxel.authRequired();
          this.showLoadModal = true;
          if (!this.searchesLoaded) this.fetchSearches();
        },
        closeLoadModal: function () {
          this.showLoadModal = false;
          this.searchQuery = "";
        },
        fetchSearches: function () {
          this.loadingSearches = true;
          var self = this;
          jQuery
            .get(Voxel_Config.ajax_url + "&action=vt_get_saved_searches", {
              page: 1,
            })
            .always(function (response) {
              self.loadingSearches = false;
              self.searchesLoaded = true;
              if (response.success && response.data) {
                self.searches = Object.values(response.data);
              }
            });
        },
        applySearch: function (search) {
          if (!search || !search.params) return;
          this.activeSearchId = search.id;
          this.activeSearchTitle = search.title || "Saved Search";
          this.saveToStorage(search);
          this.closeLoadModal();
          this.redirectWithFilters(search.params);
        },
        clearSearch: function () {
          this.activeSearchId = null;
          this.activeSearchTitle = null;
          this.removeFromStorage();
          this.closeLoadModal();
          var base =
            this.$root.post_type?.archive_link || window.location.pathname;
          window.location.href = base;
        },
        redirectWithFilters: function (params) {
          if (!params) return;
          var base =
            this.$root.post_type?.archive_link || window.location.pathname;
          var urlParams = new URLSearchParams();
          Object.keys(params).forEach(function (key) {
            if (key === "post_type") return;
            var val = params[key];
            if (val !== null && val !== undefined && val !== "")
              urlParams.set(key, val);
          });
          var qs = urlParams.toString();
          window.location.href = qs ? base + "?" + qs : base;
        },
        saveToStorage: function (search) {
          try {
            localStorage.setItem(
              this.storageKey,
              JSON.stringify({
                id: search.id,
                title: search.title,
                params: search.params,
              })
            );
          } catch (e) {
            /* silent */
          }
        },
        removeFromStorage: function () {
          try {
            localStorage.removeItem(this.storageKey);
          } catch (e) {
            /* silent */
          }
        },
        checkAutoApply: function () {
          if (!Voxel_Config.is_logged_in) return;
          var stored;
          try {
            stored = JSON.parse(localStorage.getItem(this.storageKey));
          } catch (e) {
            return;
          }
          if (!stored || !stored.id || !stored.params) return;
          this.activeSearchId = stored.id;
          this.activeSearchTitle = stored.title || "Saved Search";

          var current = new URLSearchParams(window.location.search);
          var hasFilters = Array.from(current.keys()).some(function (k) {
            return k !== "post_type";
          });
          if (!hasFilters) {
            var urlParams = new URLSearchParams();
            Object.keys(stored.params).forEach(function (key) {
              if (key === "post_type") return;
              var val = stored.params[key];
              if (val !== null && val !== undefined && val !== "")
                urlParams.set(key, val);
            });
            if (urlParams.toString()) {
              var self = this;
              setTimeout(function () {
                self.redirectWithFilters(stored.params);
              }, 100);
            }
          }
        },
      },
    });
  });

  /* ══════════════════════════════════════════
     PART 2 — Management Widget (Vault)
  ══════════════════════════════════════════ */
  window.render_vtk_search_vault = function () {
    if (typeof Vue === "undefined" || typeof Voxel === "undefined") {
      setTimeout(window.render_vtk_search_vault, 100);
      return;
    }

    Array.from(document.querySelectorAll(".vtk-vault")).forEach(function (el) {
      if (el.__vue_app__) return;

      var vtConfig = {};
      try {
        vtConfig = JSON.parse(el.dataset.config || "{}");
      } catch (e) {
        /* silent */
      }

      var vaultApp = Vue.createApp({
        el: el,
        data: function () {
          return {
            config: vtConfig,
            searches: {},
            page: 1,
            loading: true,
            hasMore: false,

            /* Delete modal */
            showDeleteModal: false,
            deleteTargetId: null,
            deleting: false,

            /* Edit title modal */
            showEditModal: false,
            editTargetId: null,
            editTitle: "",
            editSaving: false,
          };
        },
        created: function () {
          this.getSearches();
        },
        computed: {
          sortedSearches: function () {
            return Object.entries(this.searches)
              .sort(function (a, b) {
                return b[0] - a[0];
              })
              .map(function (pair) {
                return pair[1];
              });
          },
        },
        methods: {
          renderCriterion: renderCriterion,
          formatDate: formatDate,
          buildSearchUrl: function (searchId) {
            return buildSearchUrl(this.searches[searchId]);
          },

          getSearches: function () {
            this.loading = true;
            var self = this;
            jQuery.get(
              Voxel_Config.ajax_url + "&action=vt_get_saved_searches",
              { page: this.page },
              function (response) {
                if (response.success) {
                  self.searches = response.data;
                  self.hasMore = response.has_more;
                }
                self.loading = false;
              }
            );
          },

          viewSearch: function (id) {
            window.location.href = this.buildSearchUrl(id);
          },

          shareSearch: function (id) {
            var url = this.buildSearchUrl(id);
            var msg =
              (this.config.labels && this.config.labels.shareSuccess) ||
              "Link copied to clipboard!";
            copyToClipboard(url, msg);
          },

          /* Notification toggle */
          toggleNotification: function (id) {
            var s = this.searches[id];
            if (!s) return;
            s.isTogglingNotification = true;
            var self = this;
            jQuery.post(
              Voxel_Config.ajax_url + "&action=vt_update_saved_search",
              {
                search_id: id,
                data: { notification: s.notification ? 0 : 1 },
              },
              function (resp) {
                s.isTogglingNotification = false;
                if (resp.success) s.notification = !s.notification;
              }
            );
          },

          /* Delete via modal */
          openDeleteModal: function (id) {
            this.deleteTargetId = id;
            this.showDeleteModal = true;
          },
          closeDeleteModal: function () {
            this.showDeleteModal = false;
            this.deleteTargetId = null;
            this.deleting = false;
          },
          confirmDelete: function () {
            if (!this.deleteTargetId || this.deleting) return;
            this.deleting = true;
            var self = this;
            var id = this.deleteTargetId;
            jQuery.post(
              Voxel_Config.ajax_url + "&action=vt_delete_saved_search",
              { search_id: id },
              function (resp) {
                self.deleting = false;
                if (resp.success) {
                  delete self.searches[id];
                }
                self.closeDeleteModal();
              }
            );
          },

          /* Edit title via modal */
          openEditModal: function (id) {
            this.editTargetId = id;
            this.editTitle = this.searches[id]?.title || "";
            this.showEditModal = true;
            var self = this;
            this.$nextTick(function () {
              var inp = document.querySelector(".vtk-dialog__edit-input");
              if (inp) inp.focus();
            });
          },
          closeEditModal: function () {
            this.showEditModal = false;
            this.editTargetId = null;
            this.editTitle = "";
            this.editSaving = false;
          },
          confirmEditTitle: function () {
            if (!this.editTargetId || this.editSaving) return;
            this.editSaving = true;
            var self = this;
            var id = this.editTargetId;
            jQuery.post(
              Voxel_Config.ajax_url + "&action=vt_update_saved_search",
              { search_id: id, data: { title: this.editTitle } },
              function (resp) {
                self.editSaving = false;
                if (resp.success) {
                  self.searches[id].title = self.editTitle;
                }
                self.closeEditModal();
              }
            );
          },

          /* Pagination */
          prevPage: function () {
            if (this.page > 1) {
              this.page--;
              this.getSearches();
            }
          },
          nextPage: function () {
            if (this.hasMore) {
              this.page++;
              this.getSearches();
            }
          },
        },
      });

      vaultApp.component("vtk-dialog", VtkDialog);
      vaultApp.mount(el);
    });
  };

  /* Auto-init */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", window.render_vtk_search_vault);
  } else {
    window.render_vtk_search_vault();
  }
  jQuery(document).on("voxel:markup-update", window.render_vtk_search_vault);
})();
