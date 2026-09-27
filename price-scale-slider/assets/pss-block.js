/* "Price Scale" block for the editor: previews the server-rendered widget; settings live in Settings > Price Scale. */
(function (blocks, element, ServerSideRender) {
  blocks.registerBlockType('pss/price-scale', {
    apiVersion: 2,
    title: 'Price Scale',
    icon: 'money-alt',
    category: 'widgets',
    description: 'Sliding-scale pricing with two plans and a currency selector.',
    supports: { html: false },
    edit: function () {
      return element.createElement(ServerSideRender, { block: 'pss/price-scale' });
    },
    save: function () { return null; },
  });
})(window.wp.blocks, window.wp.element, window.wp.serverSideRender);
