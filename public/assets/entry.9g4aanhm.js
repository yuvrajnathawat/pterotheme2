function A(j,k=2){if(j<1)return"0 Bytes";k=Math.floor(Math.max(0,k));let q=Math.floor(Math.log(j)/Math.log(1024));return`${Number((j/Math.pow(1024,q)).toFixed(k))} ${["Bytes","KiB","MiB","GiB","TiB"][q]}`}function C(j){return/([a-f0-9:]+:+)+[a-f0-9]+/.test(j)?`[${j}]`:j}
export{A as lc,C as mc};
