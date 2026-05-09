import{ye as h}from"/assets/entry.v7a6he4k.js";import{$k as d,_k as e}from"/assets/entry.jvjvcmey.js";import{cl as k}from"/assets/entry.d5x3f9ta.js";import{dl as g}from"/assets/entry.90252mjk.js";var m=g(k(),1);var w=({checked:r,onChange:n,className:l,id:a,disabled:o,...c})=>{let t=m.default.useMemo(()=>a||`checkbox-${Math.random().toString(36).substr(2,9)}`,[a]);return d("div",{className:"relative flex items-center",children:[e("input",{type:"checkbox",checked:r,onChange:n,className:"sr-only",id:t,disabled:o,...c}),e("label",{htmlFor:t,className:`
                    inline-flex items-center justify-center min-w-[20px] min-h-[20px] rounded-lg border-2 
                    ${r?"bg-hyper-accent border-hyper-accent":"bg-transparent border-hyper-accent"}
                    ${o?"opacity-50 cursor-not-allowed":"cursor-pointer"}
                    transition-all duration-200
                    ${l||""}
                `,children:r&&e("svg",{className:"w-3 h-3 text-white",fill:"none",stroke:"currentColor",viewBox:"0 0 24 24",children:e("path",{strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:2,d:"M5 13l4 4L19 7"})})})]})},y=({name:r,value:n,className:l,id:a,disabled:o,...c})=>e(h,{name:r,children:({field:t,form:p})=>{if(!Array.isArray(t.value))return null;let s=(t.value||[]).includes(n),u=a||`checkbox-${r}-${n}`;return d("div",{className:"relative flex items-center",children:[e("input",{...c,id:u,type:"checkbox",disabled:o,checked:s,className:"sr-only",onClick:()=>p.setFieldTouched(t.name,!0),onChange:(b)=>{let i=new Set(t.value);i.has(n)?i.delete(n):i.add(n),t.onChange(b),p.setFieldValue(t.name,Array.from(i))}}),e("label",{htmlFor:u,className:`
                            inline-flex items-center justify-center min-w-[20px] min-h-[20px] rounded-lg border-2 
                            ${s?"bg-hyper-accent border-hyper-accent":"bg-transparent border-hyper-accent"}
                            ${o?"opacity-50 cursor-not-allowed":"cursor-pointer"}
                            transition-all duration-200
                            ${l||""}
                        `,children:s&&e("svg",{className:"w-3 h-3 text-white",fill:"none",stroke:"currentColor",viewBox:"0 0 24 24",children:e("path",{strokeLinecap:"round",strokeLinejoin:"round",strokeWidth:2,d:"M5 13l4 4L19 7"})})})]})}}),x=y;
export{w as Ec,x as Fc};
