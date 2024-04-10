import MakairaListingPlugin from "./js/plugin/listing/listing.plugin";

const PluginManager = window.PluginManager;

// Plugin override
PluginManager.override("Listing", MakairaListingPlugin, "[data-listing]");


// Hot module replacement
if (module.hot) {
    module.hot.accept();
}
