import{ff as C}from"/assets/entry.xjehe85a.js";import{Oj as A,Qj as B}from"/assets/entry.fqyvsr1c.js";var N=({query:E,perPage:f,sort:k,...F})=>{return new Promise((G,H)=>{B.get("/api/client",{params:{"filter[*]":E,...F,...f?{per_page:f}:{},...k?{sort:k}:{}}}).then(({data:z})=>G({items:(z.data||[]).map((I)=>C(I)),pagination:A(z.meta.pagination)})).catch(H)})};
export{N as s};
