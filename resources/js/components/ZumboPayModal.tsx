import React, { useEffect, useMemo, useState } from 'react';

export interface ZumboPayModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    amount: number; // Em Meticais (ex: 1500.00)
    reference: string;
    initialPhone?: string;
    customerName?: string;
    endpoints: {
        stkUrl: string;       // Endpoint POST para disparar STK Push
        checkoutUrl?: string; // Endpoint POST para gerar URL de checkout com cartão
        statusUrl: string;    // Endpoint GET para verificar status
    };
    onSuccess?: () => void;
}

export default function ZumboPayModal({
    open,
    onOpenChange,
    amount,
    reference,
    initialPhone = '',
    endpoints,
    onSuccess,
}: ZumboPayModalProps) {
    const [method, setMethod] = useState<'mobile' | 'card'>('mobile');
    const [phone, setPhone] = useState(initialPhone);
    const [loading, setLoading] = useState(false);
    const [stkSent, setStkSent] = useState(false);
    const [checkoutLink, setCheckoutLink] = useState<string | null>(null);
    const [isPaid, setIsPaid] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setPhone(initialPhone);
            setLoading(false);
            setStkSent(false);
            setCheckoutLink(null);
            setIsPaid(false);
            setErrorMsg(null);
        }
    }, [open, initialPhone]);

    // Polling contínuo de status
    useEffect(() => {
        if ((!stkSent && !checkoutLink) || isPaid || !open) return;

        const interval = setInterval(async () => {
            try {
                const res = await fetch(endpoints.statusUrl);
                if (res.ok) {
                    const data = await res.json();
                    if (data.is_paid || data.pago || data.status === 'success') {
                        setIsPaid(true);
                        setStkSent(false);
                        setCheckoutLink(null);
                        clearInterval(interval);
                        if (onSuccess) setTimeout(onSuccess, 1500);
                    }
                }
            } catch {
                // Silêncio em falhas transitórias de conexão
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [stkSent, checkoutLink, isPaid, open, endpoints.statusUrl, onSuccess]);

    // Deteção inteligente da operadora moçambicana
    const operator = useMemo(() => {
        const clean = phone.replace(/\D/g, '');
        const local = clean.startsWith('258') ? clean.slice(3) : clean;

        if (local.startsWith('84') || local.startsWith('85')) {
            return { name: 'M-Pesa (Vodacom)', color: 'bg-red-50 text-red-700 border-red-200' };
        }
        if (local.startsWith('86') || local.startsWith('87')) {
            return { name: 'e-Mola (Movitel)', color: 'bg-orange-50 text-orange-700 border-orange-200' };
        }
        if (local.startsWith('82') || local.startsWith('83')) {
            return { name: 'mKesh (Tmcel)', color: 'bg-amber-50 text-amber-700 border-amber-200' };
        }
        return null;
    }, [phone]);

    const handleStkSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrorMsg(null);

        try {
            const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const res = await fetch(endpoints.stkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    telefone: phone,
                    reference: reference,
                }),
            });

            const data = await res.json();

            if (data.success) {
                if (data.status === 'success') {
                    setIsPaid(true);
                } else {
                    setStkSent(true);
                }
            } else {
                setErrorMsg(data.message || 'Não foi possível enviar o pedido de pagamento.');
            }
        } catch {
            setErrorMsg('Erro de conexão ao comunicar com o servidor.');
        } finally {
            setLoading(false);
        }
    };

    const handleCheckoutSubmit = async () => {
        if (!endpoints.checkoutUrl) return;

        setLoading(true);
        setErrorMsg(null);

        try {
            const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const res = await fetch(endpoints.checkoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ reference }),
            });

            const data = await res.json();

            if (data.success && data.checkout_url) {
                setCheckoutLink(data.checkout_url);
                window.open(data.checkout_url, '_blank');
            } else {
                setErrorMsg(data.message || 'Não foi possível gerar a página de pagamento seguro.');
            }
        } catch {
            setErrorMsg('Erro de conexão ao gerar link de pagamento.');
        } finally {
            setLoading(false);
        }
    };

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                {/* Cabeçalho */}
                <div className="p-6 border-b border-slate-100 dark:border-slate-800">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white">Pagamento Online</h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Valor total:{' '}
                        <span className="font-semibold text-slate-900 dark:text-white">
                            {amount.toLocaleString('pt-MZ', { style: 'currency', currency: 'MZN' })}
                        </span>
                    </p>
                </div>

                <div className="p-6">
                    {isPaid ? (
                        <div className="py-8 text-center space-y-3">
                            <div className="w-14 h-14 bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto">
                                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h4 className="text-xl font-bold text-emerald-600 dark:text-emerald-400">Pagamento Confirmado!</h4>
                            <p className="text-sm text-slate-500 dark:text-slate-400">A sua transação foi concluída e validada em tempo real.</p>
                        </div>
                    ) : stkSent ? (
                        <div className="py-6 text-center space-y-4">
                            <div className="relative mx-auto w-16 h-16 bg-blue-50 dark:bg-blue-950 text-blue-600 rounded-full flex items-center justify-center">
                                <svg className="w-8 h-8 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span className="absolute inset-0 rounded-full border-2 border-blue-500 animate-ping opacity-75" />
                            </div>
                            <div>
                                <h4 className="font-semibold text-lg text-slate-900 dark:text-white">Confirme no seu telemóvel</h4>
                                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                    Enviámos um pedido de autorização para <strong>{phone}</strong>. Digite o seu <strong>PIN</strong> para concluir.
                                </p>
                            </div>
                            <div className="flex items-center justify-center gap-2 text-xs text-slate-400">
                                <span className="w-2 h-2 rounded-full bg-blue-600 animate-ping" /> Aguardando autorização...
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {/* Abas */}
                            <div className="grid grid-cols-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg gap-1">
                                <button
                                    type="button"
                                    onClick={() => setMethod('mobile')}
                                    className={`py-2 text-xs font-semibold rounded-md transition ${method === 'mobile' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900'}`}
                                >
                                    Carteira Móvel
                                </button>
                                {endpoints.checkoutUrl && (
                                    <button
                                        type="button"
                                        onClick={() => setMethod('card')}
                                        className={`py-2 text-xs font-semibold rounded-md transition ${method === 'card' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900'}`}
                                    >
                                        Cartão Bancário
                                    </button>
                                )}
                            </div>

                            {errorMsg && (
                                <div className="p-3 text-xs bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 rounded-lg border border-red-200 dark:border-red-900">
                                    {errorMsg}
                                </div>
                            )}

                            {method === 'mobile' ? (
                                <form onSubmit={handleStkSubmit} className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Número de Telemóvel (M-Pesa, e-Mola ou mKesh)
                                        </label>
                                        <input
                                            type="tel"
                                            placeholder="Ex: 84XXXXXXX, 86XXXXXXX ou 82XXXXXXX"
                                            value={phone}
                                            onChange={(e) => setPhone(e.target.value)}
                                            className="w-full px-3 py-2 text-sm border rounded-lg dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required
                                        />
                                    </div>

                                    {operator && (
                                        <div className="flex items-center gap-2">
                                            <span className={`text-[11px] font-semibold px-2 py-0.5 rounded border ${operator.color}`}>
                                                {operator.name}
                                            </span>
                                        </div>
                                    )}

                                    <button
                                        type="submit"
                                        disabled={loading || phone.replace(/\D/g, '').length < 9}
                                        className="w-full py-2.5 px-4 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg transition"
                                    >
                                        {loading ? 'A enviar pedido...' : 'Pagar via STK Push'}
                                    </button>
                                </form>
                            ) : (
                                <div className="space-y-4 text-center py-2">
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Pague de forma rápida e segura utilizando cartões Visa ou Mastercard.
                                    </p>
                                    <button
                                        type="button"
                                        onClick={handleCheckoutSubmit}
                                        disabled={loading}
                                        className="w-full py-2.5 px-4 text-sm font-semibold text-white bg-slate-900 hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 rounded-lg transition"
                                    >
                                        {loading ? 'A gerar link...' : 'Abrir Checkout com Cartão'}
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Rodapé */}
                <div className="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    );
}
