<div id="hg-stats">
    <br />
    <br />
    <div class="content">
        <h1> Hosted Graphite Stats Settings </h1>
        <form class="pure-form pure-form-aligned" method="POST" action="">
            <fieldset>

                <div class="pure-control-group">
                    <label for="hg_stats_api_key">API Key</label>
                    <input id="hg_stats_api_key" name="hg_stats_api_key" value="{{ $hg_stats_api_key }}" type="text" placeholder="Hosted Graphite API Key">
                </div>

                <div class="pure-controls">
                    <input type="hidden" name="form_submitted" value="true">
                    <button type="submit" class="pure-button pure-button-primary">Submit</button>
                </div>
            </fieldset>
        </form>
    </div>
</div>
