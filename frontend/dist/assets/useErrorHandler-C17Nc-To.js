import{b as n}from"./index-BnBnyITJ.js";function f(){const{showToast:a}=n();return{handleError:(e,t="An unexpected error occurred")=>{let s=t;if(e&&typeof e=="object"&&"response"in e){const o=e.response?.data;o?.messages&&typeof o.messages=="object"?s=Object.values(o.messages).join(`
`):o?.message&&(s=o.message)}else e instanceof Error&&(s=e.message);return a(s,"error"),s}}}export{f as u};
