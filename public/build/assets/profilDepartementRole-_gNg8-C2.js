import{c as t}from"./createLucideIcon-DWP9KzoJ.js";/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const l=t("BadgeCheckIcon",[["path",{d:"M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z",key:"3c2336"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]]);/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const o=t("CircleUserIcon",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["circle",{cx:"12",cy:"10",r:"3",key:"ilqhr7"}],["path",{d:"M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662",key:"154egf"}]]);function n(r){let e=r.replace(/informatique/i,"IT");return e=e.replace(/^direction\s+/i,""),e=e.replace(/^departement\s+/i,""),/exploitation/i.test(e)&&(e="EXPLOITATION"),/controle\s+permanent|contrôle\s+permanent/i.test(e)&&(e="CONTROLE PERMANENT"),e.toUpperCase().trim().replace(/É|È|Ê|Ë/g,"E").replace(/À|Â/g,"A").replace(/Ç/g,"C").replace(/Ô|Ö/g,"O").replace(/Û|Ü/g,"U").replace(/Î|Ï/g,"I").replace(/Ù|Ú/g,"U")}function p(r,e,c){if(!r?.trim())return null;const a=n(r);return e[a]??c}export{l as B,o as C,p as r};
